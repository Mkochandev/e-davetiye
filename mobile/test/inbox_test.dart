import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zarf/features/inbox/data/invitation_actions.dart';
import 'package:zarf/features/inbox/ui/envelope_open_screen.dart';

import 'support/fake_api.dart';

void main() {
  late FakeApi api;

  setUp(() {
    api = FakeApi()..on('GET /me', (_, _) => FakeResponse(200, userJson(name: 'Ayşe', consented: true)));
  });

  testWidgets('groups envelopes into unopened and opened sections', (tester) async {
    api.on(
      'GET /inbox',
      (_, _) => FakeResponse(200, [
        envelopeJson(1, title: 'Elif & Emre'),
        envelopeJson(2, title: 'Zeynep & Can', opened: true, rsvp: 'attending'),
        envelopeJson(3, title: 'Ali Kına', opened: true, cancelled: true),
      ]),
    );

    await pumpApp(tester, api, token: 't');

    expect(find.text('AÇILMAMIŞ'), findsOneWidget);
    expect(find.text('AÇILMIŞ'), findsOneWidget);
    expect(tester.getTopLeft(find.text('Elif & Emre')).dy, lessThan(tester.getTopLeft(find.text('AÇILMIŞ')).dy));
    expect(find.text('Yeni'), findsOneWidget);
    expect(find.text('Katılıyorsun'), findsOneWidget);
    expect(find.text('İptal edildi'), findsOneWidget);
    expect(find.text('12 Haziran 2027, Cumartesi · 19:00'), findsNWidgets(3));
  });

  testWidgets('shows an error with retry, then refreshes', (tester) async {
    var fail = true;
    api.on('GET /inbox', (request, _) {
      if (fail) throw DioUnreachable(request);
      return FakeResponse(200, [envelopeJson(1)]);
    });

    await pumpApp(tester, api, token: 't');
    expect(find.text('Sunucuya ulaşılamadı. İnternet bağlantınızı kontrol edin.'), findsOneWidget);

    fail = false;
    await tester.tap(find.text('Tekrar dene'));
    await tester.pumpAndSettle();
    expect(find.text('Elif & Emre'), findsOneWidget);
  });

  testWidgets('opens an envelope: seal tap → haptic + open call → read the invitation', (tester) async {
    final haptics = <String>[];
    tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, (call) async {
      if (call.method == 'HapticFeedback.vibrate') haptics.add(call.arguments as String);
      return null;
    });
    var opened = false;
    api
      ..on('GET /inbox', (_, _) => FakeResponse(200, opened ? [envelopeJson(5, opened: true)] : [envelopeJson(5)]))
      ..on('GET /inbox/5', (_, _) => FakeResponse(200, envelopeDetailJson(5)))
      ..on('POST /inbox/5/open', (_, _) {
        opened = true;
        return const FakeResponse(204);
      });

    await pumpApp(tester, api, token: 't');
    await tester.tap(find.byKey(const Key('envelope-5')));
    await tester.pumpAndSettle();

    expect(find.byType(EnvelopeOpenScreen), findsOneWidget);
    expect(find.text('Mührü açmak için dokun'), findsOneWidget);
    expect(find.text('Davetiyeyi oku'), findsNothing);

    await tester.tap(find.byKey(const Key('envelope-seal')));
    await tester.pump();
    await tester.pump(EnvelopeOpenScreen.duration ~/ 2);
    expect(find.byKey(const Key('envelope-seal')), findsNothing, reason: 'mühür kaybolur');
    await tester.pumpAndSettle();

    expect(haptics, ['HapticFeedbackType.mediumImpact']);
    expect(api.calls, contains('POST /inbox/5/open'));

    await tester.tap(find.text('Davetiyeyi oku'));
    await tester.pumpAndSettle();
    expect(find.text('Katılım durumun'), findsOneWidget);
    expect(find.text('Gül Düğün Salonu'), findsOneWidget);

    // Geri dönünce zarf "Açılmış" bölümünde.
    await tester.tap(find.byType(BackButton));
    await tester.pumpAndSettle();
    expect(find.text('AÇILMIŞ'), findsOneWidget);
  });

  testWidgets('goes straight to the invitation for an opened envelope', (tester) async {
    api
      ..on('GET /inbox', (_, _) => FakeResponse(200, [envelopeJson(5, opened: true)]))
      ..on('GET /inbox/5', (_, _) => FakeResponse(200, envelopeDetailJson(5)));

    await pumpApp(tester, api, token: 't');
    await tester.tap(find.byKey(const Key('envelope-5')));
    await tester.pumpAndSettle();

    expect(find.byType(EnvelopeOpenScreen), findsNothing);
    expect(find.text('Katılım durumun'), findsOneWidget);
    expect(api.calls, isNot(contains('POST /inbox/5/open')));
  });

  test('builds directions links for each platform', () {
    expect(
      InvitationActions.directionsUri(37.0662, 37.3833, platform: TargetPlatform.android).toString(),
      'https://www.google.com/maps/dir/?api=1&destination=37.0662%2C37.3833',
    );
    expect(
      InvitationActions.directionsUri(37.0662, 37.3833, platform: TargetPlatform.iOS).toString(),
      'https://maps.apple.com/?daddr=37.0662%2C37.3833',
    );
  });
}
