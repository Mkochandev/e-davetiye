import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:zarf/app.dart';
import 'package:zarf/core/api/api_client.dart';
import 'package:zarf/core/router/router.dart';
import 'package:zarf/core/storage/token_storage.dart';
import 'package:zarf/features/events/data/contact_phone.dart';
import 'package:zarf/features/events/data/contacts_source.dart';

import 'support/fake_api.dart';

class FakeContacts implements ContactsSource {
  FakeContacts(this.contacts, {this.access = ContactsAccess.granted});

  final List<PhoneContact> contacts;
  final ContactsAccess access;
  bool asked = false;

  @override
  Future<ContactsAccess> requestAccess() async {
    asked = true;
    return access;
  }

  @override
  Future<List<PhoneContact>> load() async => contacts;
}

Future<void> pumpWith(WidgetTester tester, FakeApi api, {ContactsSource? contacts}) async {
  await initializeDateFormatting('tr_TR');
  // Uzun telefon ekranı: formlar ve listeler kaydırmadan görünsün.
  tester.view.physicalSize = const Size(1290, 4800);
  tester.view.devicePixelRatio = 3;
  addTearDown(tester.view.reset);
  api
    ..routes.putIfAbsent(
      'GET /me',
      () =>
          (_, _) => FakeResponse(200, userJson(name: 'Elif', consented: true)),
    )
    ..routes.putIfAbsent(
      'GET /inbox',
      () =>
          (_, _) => const FakeResponse(200, []),
    );
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
        dioProvider.overrideWith((ref) => createDio(ref)..httpClientAdapter = api),
        if (contacts != null) contactsSourceProvider.overrideWithValue(contacts),
      ],
      child: const ZarfApp(),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> go(WidgetTester tester, String location) async {
  GoRouter.of(tester.element(find.byType(Scaffold).first)).push(location);
  await tester.pumpAndSettle();
}

void main() {
  late FakeApi api;

  setUp(() => api = FakeApi());

  testWidgets('M9 lists events with the salon badge and M10 creates one in Istanbul time', (tester) async {
    Map<String, dynamic>? created;
    api
      ..on('GET /events', (_, _) => FakeResponse(200, [eventJson(1, fromSalon: true, status: 'sent')]))
      ..on('POST /events', (_, body) {
        created = body;
        return FakeResponse(201, eventJson(2, title: 'Ayşe Doğum Günü'));
      })
      ..on('GET /events/2', (_, _) => FakeResponse(200, eventJson(2, title: 'Ayşe Doğum Günü')));
    await pumpWith(tester, api);

    await tester.tap(find.text('Etkinliklerim').last);
    await tester.pumpAndSettle();
    expect(find.text('Salon tarafından eklendi'), findsOneWidget);
    expect(find.text('Davetiye gönderildi'), findsOneWidget);

    await tester.tap(find.text('Yeni etkinlik'));
    await tester.pumpAndSettle();

    // Boş formda Türkçe uyarılar, istek yok.
    await tester.tap(find.text('Etkinliği oluştur'));
    await tester.pumpAndSettle();
    expect(find.text('Tarih seç.'), findsOneWidget);
    expect(created, isNull);

    await tester.tap(find.text('Doğum günü'));
    await tester.enterText(find.byKey(const Key('event-title')), 'Ayşe Doğum Günü');
    await tester.enterText(find.byKey(const Key('event-venue')), 'Ev');
    await tester.tap(find.byKey(const Key('event-date')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('15'));
    await tester.tap(find.text('Tamam'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Etkinliği oluştur'));
    await tester.pumpAndSettle();

    expect(created!['type'], 'birthday');
    expect(created!['starts_at'], matches(RegExp(r'^\d{4}-\d{2}-15T19:00:00\+03:00$')));
    expect(find.text('Davetiye henüz yok'), findsOneWidget);
  });

  testWidgets('M11 salon event toggles headcount sharing', (tester) async {
    bool? shared;
    api
      ..on('GET /events/1', (_, _) => FakeResponse(200, eventJson(1, fromSalon: true, status: 'draft')))
      ..on('PATCH /events/1/headcount-sharing', (_, body) {
        shared = body['share'] as bool;
        return FakeResponse(200, eventJson(1, fromSalon: true, status: 'draft'));
      });
    await pumpWith(tester, api);
    await go(tester, Routes.eventFor(1));

    expect(find.text('Tarih, saat ve mekanı Gül Salonu günceller.'), findsOneWidget);
    expect(find.text('Davetiye taslak'), findsOneWidget);
    await tester.tap(find.byKey(const Key('headcount-switch')));
    await tester.pumpAndSettle();
    expect(shared, isFalse);
  });

  group('M12 editor', () {
    Map<String, dynamic>? saved;

    setUp(() {
      saved = null;
      api
        ..on('GET /events/1', (_, _) => FakeResponse(200, eventJson(1)))
        ..on('GET /events/1/invitation', (_, _) => const FakeResponse(404, {'message': 'Kayıt bulunamadı.'}))
        ..on('GET /templates', (_, _) => FakeResponse(200, templatesJson()))
        ..on('PUT /events/1/invitation', (_, body) {
          saved = body;
          return FakeResponse(200, {
            'id': 9,
            'event_id': 1,
            'template': templatesJson().first,
            'texts': body['texts'],
            'envelope_color': body['envelope_color'],
            'seal_color': body['seal_color'],
            'seal_text': body['seal_text'],
            'rsvp_deadline': null,
            'first_sent_at': null,
          });
        });
    });

    testWidgets('starts from type defaults, previews live and saves', (tester) async {
      await pumpWith(tester, api);
      await go(tester, Routes.editorFor(1));

      expect(find.byKey(const Key('card-preview')), findsOneWidget);
      expect(find.text('DÜĞÜN DAVETİ'), findsOneWidget);

      await tester.tap(find.text('İleri'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('text-intro')), 'Yılmaz ve Demir aileleri olarak');
      await tester.pumpAndSettle();
      // Önizleme yazarken güncellenir.
      expect(
        find.descendant(
          of: find.byKey(const Key('card-preview')),
          matching: find.text('Yılmaz ve Demir aileleri olarak'),
        ),
        findsOneWidget,
      );

      await tester.tap(find.text('İleri'));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('envelope-preview')), findsOneWidget);
      await tester.tap(find.byKey(const Key('envelope-color-navy')));
      await tester.enterText(find.byKey(const Key('seal-text')), 'E&E');
      await tester.tap(find.text('Kaydet'));
      await tester.pumpAndSettle();

      expect(saved!['template_id'], 1);
      expect(saved!['envelope_color'], 'navy');
      expect(saved!['seal_color'], 'bordo');
      expect(saved!['seal_text'], 'E&E');
      expect((saved!['texts'] as Map)['intro'], 'Yılmaz ve Demir aileleri olarak');
      expect((saved!['texts'] as Map)['names'], 'Elif & Emre');
    });

    testWidgets('locks premium templates and colors behind the pack', (tester) async {
      await pumpWith(tester, api);
      await go(tester, Routes.editorFor(1));

      await tester.tap(find.byKey(const Key('template-altin-varak')));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('paywall-placeholder')), findsOneWidget);
      await tester.tap(find.byType(BackButton));
      await tester.pumpAndSettle();

      await tester.tap(find.text('3. Zarf'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('seal-color-gold')));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('paywall-placeholder')), findsOneWidget);
      expect(saved, isNull);
    });

    testWidgets('opens the paywall when the server answers 402', (tester) async {
      api.on(
        'PUT /events/1/invitation',
        (_, _) => const FakeResponse(402, {
          'code': 'pack_required',
          'message': 'Premium şablonlar için Etkinlik Paketi gerekiyor.',
        }),
      );
      await pumpWith(tester, api);
      await go(tester, Routes.editorFor(1));
      await tester.tap(find.text('3. Zarf'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Kaydet'));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('paywall-placeholder')), findsOneWidget);
    });
  });

  group('M13 contacts', () {
    final contacts = [for (var i = 0; i < 8; i++) PhoneContact(name: 'Kişi $i', phone: '+90536000000$i')];
    List<dynamic>? added;
    List<dynamic>? matched;

    setUp(() {
      added = null;
      matched = null;
      api
        ..on('GET /events/1', (_, _) => FakeResponse(200, eventJson(1, status: 'draft')))
        ..on(
          'GET /events/1/invitation/recipients',
          (_, _) => FakeResponse(200, [
            for (var i = 0; i < 25; i++) recipientJson(100 + i, '+9053700000${i.toString().padLeft(2, '0')}'),
            recipientJson(200, '+905360000000', name: 'Kişi 0'),
          ]),
        )
        ..on('POST /contacts/match', (_, body) {
          matched = body['hashes'] as List;
          return FakeResponse(200, {
            'registered': [ContactPhone.hash('+905360000001')],
          });
        })
        ..on('POST /events/1/invitation/recipients', (_, body) {
          added = body['recipients'] as List;
          return FakeResponse(201, {'added': added, 'skipped_count': 0, 'total': 26 + added!.length, 'limit': 30});
        });
    });

    testWidgets('explains before asking, sends only hashes, badges app users and counts to 30', (tester) async {
      final source = FakeContacts(contacts);
      await pumpWith(tester, api, contacts: source);
      await go(tester, Routes.recipientsFor(1));
      expect(find.text('26/30'), findsOneWidget);

      await tester.tap(find.byKey(const Key('pick-contacts')));
      await tester.pumpAndSettle();
      expect(source.asked, isFalse, reason: 'izin öncesi açıklama ekranı');
      await tester.tap(find.byKey(const Key('allow-contacts')));
      await tester.pumpAndSettle();

      expect(matched, hasLength(8));
      expect(matched!.every((h) => RegExp(r'^[0-9a-f]{64}$').hasMatch(h as String)), isTrue);
      expect(find.text('Uygulamada'), findsOneWidget);
      expect(find.text('26/30'), findsOneWidget);
      expect(find.textContaining('listede'), findsOneWidget, reason: 'zaten eklenmiş kişi seçilemez');

      for (var i = 1; i <= 4; i++) {
        await tester.tap(find.text('Kişi $i'));
      }
      await tester.pumpAndSettle();
      expect(find.text('30/30'), findsOneWidget);
      expect(find.text('4 kişiyi ekle'), findsOneWidget);

      await tester.tap(find.text('Kişi 5'));
      await tester.pumpAndSettle();
      expect(find.text('31/30'), findsOneWidget);
      expect(find.text('Ücretsiz sınır aşıldı'), findsOneWidget);
      await tester.tap(find.text('Sınırı kaldır').last);
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('paywall-placeholder')), findsOneWidget);
      expect(added, isNull);

      await tester.tap(find.byType(BackButton));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Kişi 5'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('4 kişiyi ekle'));
      await tester.pumpAndSettle();

      expect(added, [
        for (var i = 1; i <= 4; i++) {'phone': '+90536000000$i', 'name': 'Kişi $i'},
      ]);
    });

    testWidgets('shows the paywall when the server rejects the batch with 402', (tester) async {
      api.on(
        'POST /events/1/invitation/recipients',
        (_, _) => const FakeResponse(402, {
          'code': 'pack_required',
          'message': 'Ücretsiz sürümde en fazla 30 davetli eklenebilir.',
        }),
      );
      await pumpWith(tester, api, contacts: FakeContacts(contacts));
      await go(tester, Routes.contactsFor(1));
      await tester.tap(find.byKey(const Key('allow-contacts')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Kişi 1'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('1 kişiyi ekle'));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('paywall-placeholder')), findsOneWidget);
    });

    testWidgets('offers manual entry when contacts permission is denied', (tester) async {
      await pumpWith(tester, api, contacts: FakeContacts(contacts, access: ContactsAccess.permanentlyDenied));
      await go(tester, Routes.contactsFor(1));
      await tester.tap(find.byKey(const Key('allow-contacts')));
      await tester.pumpAndSettle();

      expect(find.textContaining('ayarlarından'), findsOneWidget);
      expect(matched, isNull);
    });

    testWidgets('adds a number manually', (tester) async {
      await pumpWith(tester, api);
      await go(tester, Routes.recipientsFor(1));
      await tester.tap(find.byKey(const Key('add-manually')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('manual-name')), 'Mehmet');
      await tester.enterText(find.byKey(const Key('manual-phone')), '0538 111 22 33');
      await tester.tap(find.text('Ekle'));
      await tester.pumpAndSettle();

      expect(added, [
        {'phone': '+905381112233', 'name': 'Mehmet'},
      ]);
    });
  });

  group('M15 send', () {
    setUp(() {
      api
        ..on('GET /events/1', (_, _) => FakeResponse(200, eventJson(1, status: 'draft')))
        ..on(
          'GET /events/1/invitation/recipients',
          (_, _) => FakeResponse(200, [
            recipientJson(1, '+905361112233', name: 'Ayşe', appUser: true),
            recipientJson(2, '+905362223344', name: 'Mehmet'),
            recipientJson(3, '+905363334455'),
            recipientJson(4, '+905364445566', sent: true, link: true),
          ]),
        )
        ..on(
          'POST /events/1/invitation/send',
          (_, _) => FakeResponse(200, {
            'in_app_count': 1,
            'link_recipients': [
              {
                'id': 2,
                'name': 'Mehmet',
                'phone': '+905362223344',
                'url': 'http://localhost/z/abc',
                'whatsapp_url': 'https://wa.me/905362223344?text=x',
              },
              {
                'id': 3,
                'name': null,
                'phone': '+905363334455',
                'url': 'http://localhost/z/def',
                'whatsapp_url': 'https://wa.me/905363334455?text=y',
              },
            ],
          }),
        );
    });

    testWidgets('summarizes unsent recipients and lists link recipients after sending', (tester) async {
      String? copied;
      tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, (call) async {
        if (call.method == 'Clipboard.setData') copied = (call.arguments as Map)['text'] as String;
        return null;
      });
      await pumpWith(tester, api);
      await go(tester, Routes.sendFor(1));

      expect(find.text('3 davetliye gönderilecek'), findsOneWidget);
      expect(find.text('1 kişi uygulamaya'), findsOneWidget);
      expect(find.text('2 kişi linkle'), findsOneWidget);

      await tester.tap(find.widgetWithText(FilledButton, 'Gönder'));
      await tester.pumpAndSettle();

      expect(find.text('1 kişinin posta kutusuna zarf düştü.'), findsOneWidget);
      expect(find.text('Linkle gönderilecekler (2)'), findsOneWidget);
      expect(find.byKey(const Key('link-recipient-2')), findsOneWidget);
      expect(find.byKey(const Key('link-recipient-3')), findsOneWidget);
      expect(find.text('0536 333 44 55'), findsOneWidget);
      expect(find.text("WhatsApp'ta gönder"), findsNWidgets(2));

      await tester.tap(
        find.descendant(of: find.byKey(const Key('link-recipient-2')), matching: find.text('Linki kopyala')),
      );
      await tester.pumpAndSettle();
      expect(copied, 'http://localhost/z/abc');
      expect(find.text('Link kopyalandı.'), findsOneWidget);
    });

    testWidgets('says there is nobody new to send to', (tester) async {
      api.on(
        'GET /events/1/invitation/recipients',
        (_, _) => FakeResponse(200, [recipientJson(4, '+905364445566', sent: true, link: true)]),
      );
      await pumpWith(tester, api);
      await go(tester, Routes.sendFor(1));

      expect(find.text('Gönderilecek yeni davetli yok'), findsOneWidget);
      expect(api.calls, isNot(contains('POST /events/1/invitation/send')));
    });
  });

  testWidgets('M16 filters recipients and offers a reminder only to pending link recipients', (tester) async {
    api
      ..on(
        'GET /events/1/invitation/stats',
        (_, _) => const FakeResponse(200, {
          'sent': 4,
          'opened': 3,
          'attending': 1,
          'attending_guests': 3,
          'not_attending': 1,
          'pending': 1,
          'unopened': 1,
        }),
      )
      ..on(
        'GET /events/1/invitation/recipients',
        (_, _) => FakeResponse(200, [
          recipientJson(1, '+905361112233', name: 'Ayşe', sent: true, opened: true, rsvp: 'attending', guests: 3),
          recipientJson(
            2,
            '+905362223344',
            name: 'Mehmet',
            sent: true,
            opened: true,
            rsvp: 'not_attending',
            link: true,
          ),
          recipientJson(3, '+905363334455', name: 'Zeynep', sent: true, opened: true, link: true),
          recipientJson(4, '+905364445566', name: 'Can', sent: true, link: true),
          recipientJson(5, '+905365556677', name: 'Gönderilmedi'),
        ]),
      );
    await pumpWith(tester, api);
    await go(tester, Routes.rsvpFor(1));

    expect(find.text('3 kişi'), findsOneWidget);
    expect(find.text('Gönderilmedi'), findsNothing);
    expect(find.text('Tümü (4)'), findsOneWidget);
    expect(find.text('Hatırlat'), findsNWidgets(2));

    await tester.ensureVisible(find.byKey(const Key('filter-unopened')));
    await tester.tap(find.byKey(const Key('filter-unopened')));
    await tester.pumpAndSettle();
    expect(find.text('Can'), findsOneWidget);
    expect(find.text('Zeynep'), findsNothing);

    await tester.ensureVisible(find.byKey(const Key('filter-pending')));
    await tester.tap(find.byKey(const Key('filter-pending')));
    await tester.pumpAndSettle();
    expect(find.text('Zeynep'), findsOneWidget);
    expect(find.text('Açtı, yanıt bekleniyor'), findsOneWidget);

    await tester.ensureVisible(find.byKey(const Key('filter-attending')));
    await tester.tap(find.byKey(const Key('filter-attending')));
    await tester.pumpAndSettle();
    expect(find.text('Katılıyor · 3 kişi'), findsOneWidget);
    expect(find.text('Hatırlat'), findsNothing);
  });
}
