import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zarf/features/auth/ui/code_screen.dart';

import 'support/fake_api.dart';

void main() {
  late FakeApi api;
  late bool profileSaved;

  setUp(() {
    profileSaved = false;
    api = FakeApi()
      ..on('POST /auth/otp', (_, _) => const FakeResponse(204))
      ..on('POST /auth/verify', (_, body) {
        if (body['code'] != '123456') {
          return const FakeResponse(422, {
            'message': 'Kod hatalı. Lütfen tekrar deneyin.',
            'errors': {
              'code': ['Kod hatalı. Lütfen tekrar deneyin.'],
            },
          });
        }
        return FakeResponse(200, {'token': 'new-token', 'user': userJson(), 'is_new': true});
      })
      ..on('PATCH /me', (_, body) {
        profileSaved = body['name'] == 'Elif Yılmaz' && body['kvkk_accepted'] == true;
        return FakeResponse(200, userJson(name: 'Elif Yılmaz', consented: true));
      });
  });

  Future<void> goToPhone(WidgetTester tester) async {
    await pumpApp(tester, api);
    await tester.tap(find.text('Başla'));
    await tester.pumpAndSettle();
  }

  Future<void> enterCode(WidgetTester tester, String code) async {
    await tester.enterText(find.byKey(const Key('code-field')), code);
    await tester.pumpAndSettle();
  }

  testWidgets('rejects a phone number that is not 10 digits without calling the API', (tester) async {
    await goToPhone(tester);

    await tester.enterText(find.byKey(const Key('phone-field')), '532 111 22');
    await tester.tap(find.text('Kod gönder'));
    await tester.pumpAndSettle();

    expect(find.text('Telefon numarası 10 haneli olmalı.'), findsOneWidget);
    expect(api.calls, isEmpty);
  });

  testWidgets('logs in with the SMS code, completes the profile and opens the inbox', (tester) async {
    await goToPhone(tester);

    await tester.enterText(find.byKey(const Key('phone-field')), '05321112233');
    expect(find.text('532 111 22 33'), findsOneWidget);
    await tester.tap(find.text('Kod gönder'));
    await tester.pumpAndSettle();

    expect(api.requests.last.data, {'phone': '+905321112233'});
    expect(find.text('Kodu gir'), findsOneWidget);
    expect(find.textContaining('0532 111 22 33'), findsOneWidget);

    // Hatalı kod: sunucunun Türkçe mesajı gösterilir, alan temizlenir.
    await enterCode(tester, '000000');
    expect(find.text('Kod hatalı. Lütfen tekrar deneyin.'), findsOneWidget);

    // Doğru kod: oturum açılır, ad ve rıza eksik olduğu için M4.
    await enterCode(tester, '123456');
    expect(find.text('Seni tanıyalım'), findsOneWidget);

    // Rıza işaretlenmeden devam edilemez.
    await tester.enterText(find.byKey(const Key('name-field')), 'Elif Yılmaz');
    await tester.tap(find.text('Devam et'));
    await tester.pumpAndSettle();
    expect(find.text('Devam etmek için açık rızanı onaylamalısın.'), findsOneWidget);
    expect(api.calls, isNot(contains('PATCH /me')));

    await tester.tap(find.byKey(const Key('consent-checkbox')));
    await tester.tap(find.text('Devam et'));
    await tester.pumpAndSettle();

    expect(profileSaved, isTrue);
    expect(api.requests.last.headers['Authorization'], 'Bearer new-token');
    expect(find.text('Posta kutun boş'), findsOneWidget);
  });

  testWidgets('offers resending the code only after 60 seconds', (tester) async {
    await goToPhone(tester);
    await tester.enterText(find.byKey(const Key('phone-field')), '5321112233');
    await tester.tap(find.text('Kod gönder'));
    await tester.pumpAndSettle();

    expect(find.text('Tekrar gönder'), findsNothing);
    expect(find.text('Yeni kod 60 sn sonra istenebilir'), findsOneWidget);

    await tester.pump(CodeScreen.resendAfter);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tekrar gönder'));
    await tester.pumpAndSettle();

    expect(api.calls.where((c) => c == 'POST /auth/otp'), hasLength(2));
    expect(find.text('Yeni kod gönderildi.'), findsOneWidget);
    expect(find.text('Tekrar gönder'), findsNothing);
  });

  testWidgets('shows the server message when the code request is throttled', (tester) async {
    api.on('POST /auth/otp', (_, _) => const FakeResponse(429, {'message': 'Yeni kod için 60 saniye bekleyin.'}));
    await goToPhone(tester);

    await tester.enterText(find.byKey(const Key('phone-field')), '5321112233');
    await tester.tap(find.text('Kod gönder'));
    await tester.pumpAndSettle();

    expect(find.text('Yeni kod için 60 saniye bekleyin.'), findsOneWidget);
    expect(find.text('Kodu gir'), findsNothing);
  });
}
