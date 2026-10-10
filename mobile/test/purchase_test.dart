import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:zarf/app.dart';
import 'package:zarf/core/api/api_client.dart';
import 'package:zarf/core/router/router.dart';
import 'package:zarf/core/storage/token_storage.dart';
import 'package:zarf/features/purchase/data/pack_store.dart';
import 'package:zarf/features/purchase/data/purchase_controller.dart';
import 'package:zarf/features/purchase/ui/paywall_screen.dart';

import 'support/fake_api.dart';
import 'support/fake_store.dart';

void main() {
  late FakeApi api;
  late FakePackStore store;
  late MemoryPendingPackEvent pending;
  late bool packOpened;
  Map<String, dynamic>? verified;

  setUp(() {
    store = FakePackStore();
    pending = MemoryPendingPackEvent();
    packOpened = false;
    verified = null;
    api = FakeApi()
      ..on('GET /me', (_, _) => FakeResponse(200, userJson(name: 'Elif', consented: true)))
      ..on('GET /inbox', (_, _) => const FakeResponse(200, []))
      ..on('GET /events', (_, _) => FakeResponse(200, [eventJson(1, hasPack: packOpened)]))
      ..on('GET /events/1', (_, _) => FakeResponse(200, eventJson(1, hasPack: packOpened, status: 'draft')))
      ..on('POST /purchases', (_, body) {
        verified = body;
        packOpened = true;
        return FakeResponse(200, {'transaction_id': 'GPA.1', 'event': eventJson(1, hasPack: true)});
      });
  });

  Future<void> pump(WidgetTester tester) async {
    await initializeDateFormatting('tr_TR');
    tester.view.physicalSize = const Size(1290, 3600);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
          dioProvider.overrideWith((ref) => createDio(ref)..httpClientAdapter = api),
          packStoreProvider.overrideWithValue(store),
          pendingPackEventProvider.overrideWithValue(pending),
        ],
        child: const ZarfApp(),
      ),
    );
    await tester.pumpAndSettle();
  }

  Future<void> openPaywall(WidgetTester tester) async {
    await pump(tester);
    final router = GoRouter.of(tester.element(find.byType(Scaffold).first));
    router.push(Routes.eventFor(1));
    await tester.pumpAndSettle();
    router.push(Routes.paywallFor(1));
    await tester.pumpAndSettle();
  }

  testWidgets('shows the store price and opens the pack after the server verifies it', (tester) async {
    store.onBuy = () => [FakePackStore.purchased('purchase-token-1')];
    await openPaywall(tester);

    expect(find.text('Satın al · ₺249,99'), findsOneWidget);
    expect(find.text('Sınırsız'), findsOneWidget);

    await tester.tap(find.byKey(const Key('buy-pack')));
    await tester.pumpAndSettle();

    expect(store.bought, ['event_pack']);
    expect(verified, {
      'platform': 'android',
      'product_id': 'event_pack',
      'event_id': 1,
      'verification_data': 'purchase-token-1',
    });
    expect(store.finished, hasLength(1), reason: 'onaydan sonra tamamlanır');
    expect(await pending.read(), isNull);
    expect(find.byType(PaywallScreen), findsNothing);
    expect(find.text('Etkinlik Paketi açıldı.'), findsOneWidget);
    expect(find.text('Etkinlik Paketi'), findsOneWidget, reason: 'etkinlik detayında paket rozeti');
  });

  testWidgets('does not complete the purchase when the server rejects it', (tester) async {
    api.on(
      'POST /purchases',
      (_, _) => const FakeResponse(422, {'code': 'verification_failed', 'message': 'Satın alma doğrulanamadı.'}),
    );
    store.onBuy = () => [FakePackStore.purchased()];
    await openPaywall(tester);

    await tester.tap(find.byKey(const Key('buy-pack')));
    await tester.pumpAndSettle();

    expect(find.text('Satın alma doğrulanamadı.'), findsOneWidget);
    expect(store.finished, isEmpty);
    expect(find.byType(PaywallScreen), findsOneWidget);
  });

  testWidgets('keeps the purchase pending when the server is unreachable', (tester) async {
    api.on('POST /purchases', (request, _) => throw DioUnreachable(request));
    store.onBuy = () => [FakePackStore.purchased()];
    await openPaywall(tester);

    await tester.tap(find.byKey(const Key('buy-pack')));
    await tester.pumpAndSettle();

    expect(find.text('Satın alma doğrulanamadı. Uygulamayı tekrar açtığında yeniden denenecek.'), findsOneWidget);
    expect(store.finished, isEmpty);
    expect(await pending.read(), 1, reason: 'açılışta aynı etkinliğe bağlanabilsin');
  });

  testWidgets('shows a waiting state for a pending payment, then completes', (tester) async {
    store.onBuy = () => [const StorePurchase(productId: packProductId, status: StorePurchaseStatus.pending)];
    await openPaywall(tester);

    await tester.tap(find.byKey(const Key('buy-pack')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('paywall-pending')), findsOneWidget);
    expect(verified, isNull);

    store.emit(FakePackStore.purchased());
    await tester.pumpAndSettle();
    expect(verified, isNotNull);
    expect(find.text('Etkinlik Paketi açıldı.'), findsOneWidget);
  });

  testWidgets('returns to idle when the user cancels in the store', (tester) async {
    store.onBuy = () => [const StorePurchase(productId: packProductId, status: StorePurchaseStatus.canceled)];
    await openPaywall(tester);

    await tester.tap(find.byKey(const Key('buy-pack')));
    await tester.pumpAndSettle();

    expect(store.unfinished, hasLength(1));
    expect(verified, isNull);
    expect(find.byKey(const Key('paywall-error')), findsNothing);
    expect(find.text('Satın al · ₺249,99'), findsOneWidget);
  });

  testWidgets('explains when the store is unavailable', (tester) async {
    store.available = false;
    await openPaywall(tester);

    expect(find.byKey(const Key('store-unavailable')), findsOneWidget);
    expect(find.byKey(const Key('buy-pack')), findsNothing);
  });

  testWidgets('says the pack is already active', (tester) async {
    packOpened = true;
    await openPaywall(tester);

    expect(find.text('Bu etkinlikte Etkinlik Paketi zaten açık.'), findsOneWidget);
    expect(find.byKey(const Key('buy-pack')), findsNothing);
  });

  testWidgets('finishes a purchase left pending from an earlier session at startup', (tester) async {
    await pending.write(1);
    await pump(tester);

    expect(store.resendCalls, 1, reason: 'oturum açılınca bekleyenler istenir');
    store.emit(FakePackStore.purchased('left-over-token'));
    await tester.pumpAndSettle();

    expect(verified!['verification_data'], 'left-over-token');
    expect(verified!['event_id'], 1);
    expect(store.finished, hasLength(1));
  });

  testWidgets('leaves an unattributable purchase unfinished', (tester) async {
    await pump(tester);
    store.emit(FakePackStore.purchased());
    await tester.pumpAndSettle();

    expect(verified, isNull);
    expect(store.finished, isEmpty);
  });
}
