import 'package:flutter_test/flutter_test.dart';
import 'package:zarf/core/router/router.dart';
import 'package:zarf/features/auth/data/app_user.dart';
import 'package:zarf/features/auth/data/session.dart';

import 'support/fake_api.dart';

void main() {
  const incomplete = SignedIn(AppUser(id: 1, phone: '+905321112233'));
  final complete = SignedIn(AppUser(id: 1, phone: '+905321112233', name: 'Elif', kvkkAcceptedAt: DateTime(2026)));

  group('redirectFor', () {
    test('waits on the splash while the session loads', () {
      expect(redirectFor(const SessionLoading(), Routes.inbox), Routes.splash);
      expect(redirectFor(const SessionLoading(), Routes.splash), isNull);
      expect(redirectFor(const SessionUnreachable('x'), Routes.splash), isNull);
    });

    test('sends signed-out users to the welcome screen but lets them log in', () {
      expect(redirectFor(const SignedOut(), Routes.splash), Routes.welcome);
      expect(redirectFor(const SignedOut(), Routes.inbox), Routes.welcome);
      expect(redirectFor(const SignedOut(), Routes.profileSetup), Routes.welcome);
      expect(redirectFor(const SignedOut(), Routes.phone), isNull);
      expect(redirectFor(const SignedOut(), Routes.code), isNull);
    });

    test('forces profile setup until name and consent exist', () {
      expect(redirectFor(incomplete, Routes.code), Routes.profileSetup);
      expect(redirectFor(incomplete, Routes.inbox), Routes.profileSetup);
      expect(redirectFor(incomplete, Routes.profileSetup), isNull);
    });

    test('keeps complete users out of the login screens', () {
      expect(redirectFor(complete, Routes.code), Routes.inbox);
      expect(redirectFor(complete, Routes.splash), Routes.inbox);
      expect(redirectFor(complete, Routes.profileSetup), Routes.inbox);
      expect(redirectFor(complete, Routes.events), isNull);
    });
  });

  group('app start', () {
    testWidgets('without a token shows the welcome screen', (tester) async {
      await pumpApp(tester, FakeApi());

      expect(find.text('Başla'), findsOneWidget);
    });

    testWidgets('with a valid token opens the tabs', (tester) async {
      final api = FakeApi()..on('GET /me', (_, _) => FakeResponse(200, userJson(name: 'Elif', consented: true)));

      await pumpApp(tester, api, token: 'valid');

      expect(find.text('Posta kutun boş'), findsOneWidget);
      expect(api.requests.every((r) => r.headers['Authorization'] == 'Bearer valid'), isTrue);

      await tester.tap(find.text('Etkinliklerim').last);
      await tester.pumpAndSettle();
      expect(find.text('Henüz etkinliğin yok'), findsOneWidget);
    });

    testWidgets('with an incomplete profile opens profile setup', (tester) async {
      final api = FakeApi()..on('GET /me', (_, _) => FakeResponse(200, userJson()));

      await pumpApp(tester, api, token: 'valid');

      expect(find.text('Seni tanıyalım'), findsOneWidget);
    });

    testWidgets('with a rejected token returns to welcome', (tester) async {
      final api = FakeApi()..on('GET /me', (_, _) => const FakeResponse(401, {'message': 'Unauthenticated.'}));

      await pumpApp(tester, api, token: 'expired');

      expect(find.text('Başla'), findsOneWidget);
    });

    testWidgets('when the server is unreachable offers a retry', (tester) async {
      var up = false;
      final api = FakeApi()
        ..on('GET /me', (request, _) {
          if (!up) throw DioUnreachable(request);
          return FakeResponse(200, userJson(name: 'Elif', consented: true));
        });

      await pumpApp(tester, api, token: 'valid');
      expect(find.text('Sunucuya ulaşılamadı. İnternet bağlantınızı kontrol edin.'), findsOneWidget);

      up = true;
      await tester.tap(find.text('Tekrar dene'));
      await tester.pumpAndSettle();
      expect(find.text('Posta kutun boş'), findsOneWidget);
    });
  });
}
