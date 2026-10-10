import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import 'envelope.dart';

class InboxRepository {
  InboxRepository(this._dio);

  final Dio _dio;

  Future<List<Envelope>> list() async {
    final response = await ApiException.guard(() => _dio.get('/inbox'));
    return (response.data as List).map((e) => Envelope.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<EnvelopeDetail> detail(int id) async {
    final response = await ApiException.guard(() => _dio.get('/inbox/$id'));
    return EnvelopeDetail.fromJson(response.data as Map<String, dynamic>);
  }

  Future<void> open(int id) => ApiException.guard(() => _dio.post('/inbox/$id/open'));

  Future<EnvelopeDetail> rsvp(int id, RsvpStatus status, int? guestCount) async {
    final response = await ApiException.guard(
      () => _dio.post(
        '/inbox/$id/rsvp',
        data: {'status': status.value, if (status == RsvpStatus.attending) 'guest_count': guestCount},
      ),
    );
    return EnvelopeDetail.fromJson(response.data as Map<String, dynamic>);
  }
}

final inboxRepositoryProvider = Provider<InboxRepository>((ref) => InboxRepository(ref.watch(dioProvider)));

/// Posta kutusu listesi; çekerek yenileme `refresh()`.
class InboxController extends AsyncNotifier<List<Envelope>> {
  @override
  Future<List<Envelope>> build() => ref.read(inboxRepositoryProvider).list();

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => ref.read(inboxRepositoryProvider).list());
  }
}

final inboxProvider = AsyncNotifierProvider<InboxController, List<Envelope>>(InboxController.new);

/// Tek zarf: detay, açma ve LCV.
class EnvelopeController extends AsyncNotifier<EnvelopeDetail> {
  EnvelopeController(this.id);

  final int id;

  InboxRepository get _repo => ref.read(inboxRepositoryProvider);

  @override
  Future<EnvelopeDetail> build() => _repo.detail(id);

  /// Mühür açıldı. Hata kullanıcıyı durdurmaz; bir sonraki açılışta yeniden denenir.
  Future<void> markOpened() async {
    try {
      await _repo.open(id);
      ref.invalidate(inboxProvider);
    } on ApiException {
      // Sessizce geç.
    }
  }

  /// @throws ApiException
  Future<void> respond(RsvpStatus status, int? guestCount) async {
    state = AsyncData(await _repo.rsvp(id, status, guestCount));
    ref.invalidate(inboxProvider);
  }
}

final envelopeProvider = AsyncNotifierProvider.family<EnvelopeController, EnvelopeDetail, int>(EnvelopeController.new);
