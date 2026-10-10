import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../auth/data/session.dart';
import '../../events/data/events_repository.dart';
import 'pack_store.dart';

class PurchaseRepository {
  PurchaseRepository(this._dio);

  final Dio _dio;

  /// `POST /purchases` (spec §6.5). Aynı işlem tekrar gelirse aynı başarılı yanıt.
  Future<void> verify({required int eventId, required String verificationData}) => ApiException.guard(
    () => _dio.post(
      '/purchases',
      data: {
        'platform': defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android',
        'product_id': packProductId,
        'event_id': eventId,
        'verification_data': verificationData,
      },
    ),
  );
}

final purchaseRepositoryProvider = Provider<PurchaseRepository>((ref) => PurchaseRepository(ref.watch(dioProvider)));

/// Satın alınan paketin hangi etkinliğe ait olduğu. Mağaza bunu taşımadığı için satın almadan önce saklanır;
/// uygulama ödeme sırasında kapanırsa açılışta bekleyen satın alma bu etkinliğe bağlanır.
abstract interface class PendingPackEvent {
  Future<int?> read();
  Future<void> write(int eventId);
  Future<void> clear();
}

class SecurePendingPackEvent implements PendingPackEvent {
  static const _key = 'pending_pack_event_id';
  final _storage = const FlutterSecureStorage();

  @override
  Future<int?> read() async => int.tryParse(await _storage.read(key: _key) ?? '');

  @override
  Future<void> write(int eventId) => _storage.write(key: _key, value: '$eventId');

  @override
  Future<void> clear() => _storage.delete(key: _key);
}

class MemoryPendingPackEvent implements PendingPackEvent {
  int? eventId;

  @override
  Future<int?> read() async => eventId;

  @override
  Future<void> write(int eventId) async => this.eventId = eventId;

  @override
  Future<void> clear() async => eventId = null;
}

final pendingPackEventProvider = Provider<PendingPackEvent>((ref) => SecurePendingPackEvent());

sealed class PackPurchaseState {
  const PackPurchaseState();
}

class PackIdle extends PackPurchaseState {
  const PackIdle();
}

/// Mağaza penceresi açık ya da sunucu doğruluyor.
class PackBusy extends PackPurchaseState {
  const PackBusy();
}

/// Ödeme onay bekliyor (ör. aile onayı, havale).
class PackPending extends PackPurchaseState {
  const PackPending();
}

class PackSucceeded extends PackPurchaseState {
  const PackSucceeded(this.eventId);
  final int eventId;
}

class PackFailed extends PackPurchaseState {
  const PackFailed(this.message);
  final String message;
}

/// Satın alma akışı (spec §12): mağaza → sunucu doğrulaması → onaylanınca tamamla.
/// Uygulama açılır açılmaz dinlemeye başlar; oturum açıkken bekleyen satın almaları da işler.
class PackPurchaseController extends Notifier<PackPurchaseState> {
  StreamSubscription<List<StorePurchase>>? _subscription;

  @override
  PackPurchaseState build() {
    final store = ref.read(packStoreProvider);
    _subscription = store.purchases.listen(_onPurchases, onError: (_) {});
    ref.onDispose(() => _subscription?.cancel());

    ref.listen(sessionProvider, (previous, next) {
      if (next is SignedIn && previous is! SignedIn) store.resendPending();
    }, fireImmediately: true);

    return const PackIdle();
  }

  PackStore get _store => ref.read(packStoreProvider);

  /// M14 "Satın al".
  Future<void> buy(int eventId, StoreProduct product) async {
    state = const PackBusy();
    try {
      await ref.read(pendingPackEventProvider).write(eventId);
      final started = await _store.buy(product);
      if (!started) state = const PackFailed('Satın alma başlatılamadı. Lütfen tekrar dene.');
    } catch (_) {
      state = const PackFailed('Satın alma başlatılamadı. Lütfen tekrar dene.');
    }
  }

  void reset() => state = const PackIdle();

  Future<void> _onPurchases(List<StorePurchase> purchases) async {
    for (final p in purchases.where((p) => p.productId == packProductId)) {
      await _handle(p);
    }
  }

  Future<void> _handle(StorePurchase p) async {
    switch (p.status) {
      case StorePurchaseStatus.pending:
        state = const PackPending();
      case StorePurchaseStatus.canceled:
        await _store.finishUnverified(p);
        await ref.read(pendingPackEventProvider).clear();
        state = const PackIdle();
      case StorePurchaseStatus.error:
        await _store.finishUnverified(p);
        state = const PackFailed('Satın alma tamamlanamadı. Ücret alınmadı.');
      case StorePurchaseStatus.purchased:
        await _verify(p);
    }
  }

  Future<void> _verify(StorePurchase p) async {
    // Oturum yoksa doğrulanamaz; tamamlanmaz, bir sonraki girişte yeniden gelir.
    if (ref.read(sessionProvider) is! SignedIn) return;

    final eventId = await ref.read(pendingPackEventProvider).read();
    if (eventId == null) {
      // Hangi etkinliğe ait olduğu bilinmiyor: tamamlanmaz (Android 3 gün içinde iade eder).
      state = const PackFailed('Satın alma bir etkinliğe bağlanamadı.');
      return;
    }

    state = const PackBusy();
    try {
      await ref.read(purchaseRepositoryProvider).verify(eventId: eventId, verificationData: p.verificationData);
      await _store.finishVerified(p);
      await ref.read(pendingPackEventProvider).clear();
      ref.invalidate(eventProvider(eventId));
      ref.invalidate(eventsProvider);
      state = PackSucceeded(eventId);
    } on ApiException catch (e) {
      // Onaylanmayan satın alma tamamlanmaz. Bağlantı sorunu (503/ağ) uygulama tekrar açılınca yeniden denenir.
      state = PackFailed(switch (e) {
        ApiException(code: 'pack_exists') => e.message,
        ApiException(code: 'verification_failed') => e.message,
        ApiException(statusCode: null) || ApiException(code: 'store_unavailable') =>
          'Satın alma doğrulanamadı. Uygulamayı tekrar açtığında yeniden denenecek.',
        _ => e.message,
      });
    }
  }
}

final packPurchaseProvider = NotifierProvider<PackPurchaseController, PackPurchaseState>(PackPurchaseController.new);
