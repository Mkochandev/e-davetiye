import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:zarf/core/router/router.dart';
import 'package:zarf/features/inbox/data/envelope.dart';
import 'package:zarf/features/inbox/data/rsvp_draft.dart';

import 'support/fake_api.dart';

void main() {
  group('RsvpDraft', () {
    test('requires a choice', () {
      expect(const RsvpDraft().validate(), 'Katılım durumunu seç.');
      expect(const RsvpDraft(status: RsvpStatus.notAttending).validate(), isNull);
    });

    test('keeps the guest count between 1 and 10', () {
      expect(
        const RsvpDraft(status: RsvpStatus.attending, guestCount: 0).validate(),
        'Kişi sayısı 1 ile 10 arasında olmalı.',
      );
      expect(
        const RsvpDraft(status: RsvpStatus.attending, guestCount: 11).validate(),
        'Kişi sayısı 1 ile 10 arasında olmalı.',
      );
      expect(const RsvpDraft(status: RsvpStatus.attending, guestCount: 10).validate(), isNull);

      var draft = const RsvpDraft(status: RsvpStatus.attending, guestCount: 10);
      expect(draft.canIncrement, isFalse);
      expect(draft.increment().guestCount, 10);
      draft = const RsvpDraft(status: RsvpStatus.attending);
      expect(draft.canDecrement, isFalse);
      expect(draft.decrement().guestCount, 1);
    });
  });

  group('M8 rsvp form', () {
    late FakeApi api;
    Map<String, dynamic>? sent;

    setUp(() {
      sent = null;
      api = FakeApi()
        ..on('GET /me', (_, _) => FakeResponse(200, userJson(name: 'Ayşe', consented: true)))
        ..on('GET /inbox/7', (_, _) => FakeResponse(200, envelopeDetailJson(7, deadline: '2027-06-01')))
        ..on('POST /inbox/7/rsvp', (_, body) {
          sent = body;
          return FakeResponse(
            200,
            envelopeDetailJson(
              7,
              rsvp: body['status'] as String,
              guests: body['guest_count'] as int?,
              responded: true,
              deadline: '2027-06-01',
            ),
          );
        });
    });

    Future<void> openInvitation(WidgetTester tester) async {
      await pumpApp(tester, api, token: 't');
      GoRouter.of(tester.element(find.byType(Scaffold).first)).push(Routes.invitationFor(7));
      await tester.pumpAndSettle();
    }

    Future<void> scrollTo(WidgetTester tester, Finder finder) async {
      await tester.scrollUntilVisible(finder, 200, scrollable: find.byType(Scrollable).last);
      await tester.pumpAndSettle();
    }

    testWidgets('sends attending with the guest count, capped at 10', (tester) async {
      await openInvitation(tester);
      expect(find.text('Elif & Emre'), findsWidgets);
      await scrollTo(tester, find.text('Yanıtı gönder'));

      // Seçim yapmadan gönderilemez.
      await tester.tap(find.text('Yanıtı gönder'));
      await tester.pumpAndSettle();
      expect(find.text('Katılım durumunu seç.'), findsOneWidget);
      expect(sent, isNull);

      await tester.tap(find.text('Katılıyorum'));
      await tester.pumpAndSettle();
      await scrollTo(tester, find.byKey(const Key('guest-plus')));
      for (var i = 0; i < 12; i++) {
        await tester.tap(find.byKey(const Key('guest-plus')), warnIfMissed: false);
      }
      await tester.pumpAndSettle();
      expect(tester.widget<Text>(find.byKey(const Key('guest-count'))).data, '10');
      expect(
        tester
            .widget<IconButton>(
              find.descendant(of: find.byKey(const Key('guest-plus')), matching: find.byType(IconButton)),
            )
            .onPressed,
        isNull,
      );

      await tester.tap(find.byKey(const Key('guest-minus')));
      await tester.pumpAndSettle();
      await scrollTo(tester, find.text('Yanıtı gönder'));
      await tester.tap(find.text('Yanıtı gönder'));
      await tester.pumpAndSettle();

      expect(sent, {'status': 'attending', 'guest_count': 9});
      expect(find.text('Yanıtın iletildi. Teşekkürler!'), findsOneWidget);
      expect(find.text('Katılıyorum · 9 kişi'), findsOneWidget);
      await scrollTo(tester, find.text('Son yanıt tarihi: 1 Haziran 2027'));
      expect(find.text('Son yanıt tarihi: 1 Haziran 2027'), findsOneWidget);
    });

    testWidgets('does not send a guest count when not attending', (tester) async {
      await openInvitation(tester);
      await scrollTo(tester, find.text('Katılamıyorum'));
      await tester.tap(find.text('Katılamıyorum'));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('guest-count')), findsNothing);

      await scrollTo(tester, find.text('Yanıtı gönder'));
      await tester.tap(find.text('Yanıtı gönder'));
      await tester.pumpAndSettle();

      expect(sent, {'status': 'not_attending'});
      expect(find.text('Katılamıyorum'), findsOneWidget);
    });

    testWidgets('shows the server message when the deadline passed meanwhile', (tester) async {
      api.on(
        'POST /inbox/7/rsvp',
        (_, _) => const FakeResponse(422, {
          'message': 'LCV süresi doldu, yanıt verilemez.',
          'errors': {
            'status': ['LCV süresi doldu, yanıt verilemez.'],
          },
        }),
      );
      await openInvitation(tester);
      await scrollTo(tester, find.text('Katılamıyorum'));
      await tester.tap(find.text('Katılamıyorum'));
      await scrollTo(tester, find.text('Yanıtı gönder'));
      await tester.tap(find.text('Yanıtı gönder'));
      await tester.pumpAndSettle();

      expect(find.text('LCV süresi doldu, yanıt verilemez.'), findsOneWidget);
    });

    testWidgets('is locked after the deadline and keeps the earlier answer visible', (tester) async {
      api.on(
        'GET /inbox/7',
        (_, _) =>
            FakeResponse(200, envelopeDetailJson(7, rsvp: 'attending', guests: 2, responded: true, rsvpOpen: false)),
      );
      await openInvitation(tester);
      await scrollTo(tester, find.byKey(const Key('rsvp-locked')));

      expect(find.text('LCV süresi doldu, yanıt verilemez.'), findsOneWidget);
      expect(find.text('Katılıyorum · 2 kişi'), findsOneWidget);
      expect(find.text('Yanıtı gönder'), findsNothing);
      expect(find.text('Yanıtı değiştir'), findsNothing);
    });

    testWidgets('shows a cancelled event without actions', (tester) async {
      api.on('GET /inbox/7', (_, _) => FakeResponse(200, envelopeDetailJson(7, cancelled: true, rsvpOpen: false)));
      await openInvitation(tester);

      expect(find.byKey(const Key('cancelled-banner')), findsOneWidget);
      expect(find.text('Takvime ekle'), findsNothing);
      await scrollTo(tester, find.byKey(const Key('rsvp-locked')));
      expect(find.text('Etkinlik iptal edildiği için yanıt verilemez.'), findsOneWidget);
    });
  });
}
