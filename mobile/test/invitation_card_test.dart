import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:zarf/core/invitation/invitation_card.dart';
import 'package:zarf/core/invitation/invitation_data.dart';
import 'package:zarf/core/invitation/palette.dart';

/// Seed şablonları (spec §11 tablosu; backend TemplateSeeder ile aynı).
const templates = <String, Map<String, dynamic>>{
  'klasik': {'paper': '#FFFFFF', 'ink': '#17182A', 'muted': '#5F6273', 'accent': '#8A1F3D', 'frame': 'double', 'frame_color': '#ECE4E8', 'display_font': 'Fraunces', 'ornament': 'dot-rule'},
  'modern': {'paper': '#FFFFFF', 'ink': '#17182A', 'muted': '#5F6273', 'accent': '#17182A', 'frame': 'none', 'display_font': 'Plus Jakarta Sans', 'ornament': 'top-band'},
  'bahar': {'paper': '#F3F6F1', 'ink': '#17182A', 'muted': '#56614F', 'accent': '#5E7C55', 'frame': 'single', 'frame_color': '#D5DFD0', 'display_font': 'Fraunces', 'ornament': 'top-band'},
  'minimal': {'paper': '#FAFAFA', 'ink': '#17182A', 'muted': '#5F6273', 'accent': '#5F6273', 'frame': 'single', 'frame_color': '#E3E4EA', 'display_font': 'Plus Jakarta Sans', 'ornament': 'none'},
  'altin-varak': {'paper': '#FFFFFF', 'ink': '#17182A', 'muted': '#5F6273', 'accent': '#A8833A', 'frame': 'double', 'frame_color': '#E6D9BC', 'display_font': 'Fraunces', 'ornament': 'dot-rule'},
  'gece-mavisi': {'paper': '#1D2340', 'ink': '#FFFFFF', 'muted': '#C9CBDA', 'accent': '#D9B46A', 'frame': 'single', 'frame_color': '#3A4266', 'display_font': 'Fraunces', 'ornament': 'dot-rule'},
};

TemplateConfig configOf(String slug) =>
    TemplateConfig.fromJson({'v': 1, 'body_font': 'Plus Jakarta Sans', ...templates[slug]!});

Future<void> pumpCard(WidgetTester tester, TemplateConfig config, {InvitationTexts? texts}) async {
  await tester.pumpWidget(MaterialApp(
    home: Scaffold(
      body: SingleChildScrollView(
        child: InvitationCard(
          config: config,
          texts: texts ??
              const InvitationTexts(
                label: 'DÜĞÜN DAVETİ',
                intro: 'Yılmaz ve Demir aileleri olarak',
                names: 'Elif & Emre',
                message: 'düğünümüzde sizi de aramızda görmekten mutluluk duyarız.',
              ),
          // 16:00 UTC = 19:00 İstanbul
          startsAt: DateTime.utc(2027, 6, 12, 16),
          venueName: 'Gül Düğün Salonu',
          venueAddress: 'Şehitkamil, Gaziantep',
        ),
      ),
    ),
  ));
}

void main() {
  setUpAll(() => initializeDateFormatting('tr_TR'));

  for (final slug in templates.keys) {
    testWidgets('draws the $slug template from its config', (tester) async {
      final raw = templates[slug]!;
      final config = configOf(slug);
      await pumpCard(tester, config);

      // Çizim sırası sabit: yukarıdan aşağıya.
      final order = ['card-label', 'card-intro', 'card-names', 'card-message', 'card-date', 'card-time', 'card-venue', 'card-address'];
      final ys = order.map((k) => tester.getTopLeft(find.byKey(Key(k))).dy).toList();
      expect(ys, orderedEquals([...ys]..sort()), reason: 'çizim sırası');

      expect(find.text('12 Haziran 2027'), findsOneWidget);
      expect(find.text('Cumartesi · 19:00'), findsOneWidget);

      // Çerçeve ve süsleme.
      expect(find.byKey(Key('card-frame-${raw['frame']}')), findsOneWidget);
      expect(find.byKey(const Key('card-dot-rule')), raw['ornament'] == 'dot-rule' ? findsOneWidget : findsNothing);
      expect(find.byKey(const Key('card-top-band')), raw['ornament'] == 'top-band' ? findsOneWidget : findsNothing);

      // Renkler ve fontlar.
      final paper = tester.widget<Container>(find.ancestor(of: find.byKey(Key('card-frame-${raw['frame']}')), matching: find.byType(Container)).last);
      expect((paper.decoration! as BoxDecoration).color, Palette.hex(raw['paper'] as String));

      final names = tester.widget<Text>(find.byKey(const Key('card-names')));
      expect(names.style!.fontFamily, raw['display_font']);
      expect(names.style!.color, Palette.hex(raw['ink'] as String));
      final label = tester.widget<Text>(find.byKey(const Key('card-label')));
      expect(label.style!.color, Palette.hex(raw['accent'] as String));
      expect(label.style!.fontFamily, 'Plus Jakarta Sans');
      final message = tester.widget<Text>(find.byKey(const Key('card-message')));
      expect(message.style!.color, Palette.hex(raw['muted'] as String));
    });
  }

  testWidgets('skips empty texts but always shows names and date', (tester) async {
    await pumpCard(tester, configOf('klasik'), texts: const InvitationTexts(names: 'Elif & Emre'));

    expect(find.byKey(const Key('card-label')), findsNothing);
    expect(find.byKey(const Key('card-intro')), findsNothing);
    expect(find.byKey(const Key('card-message')), findsNothing);
    expect(find.text('Elif & Emre'), findsOneWidget);
    expect(find.text('12 Haziran 2027'), findsOneWidget);
  });

  test('envelope shades match the web (× 0.88 and × 0.62)', () {
    final sage = Palette.envelope('sage');
    expect(Palette.scale(sage, Palette.flapFactor), const Color(0xFFA1AC9C));
    expect(Palette.scale(sage, Palette.innerFactor), const Color(0xFF717A6E));
  });
}
