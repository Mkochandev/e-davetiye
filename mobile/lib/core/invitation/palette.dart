import 'dart:ui';

/// Zarf ve mühür renkleri (spec §15). Sunucu `config/zarf.php` ve web `Palette` ile aynı.
abstract final class Palette {
  static const envelopeColors = {
    'sage': Color(0xFFB7C4B1),
    'navy': Color(0xFF2E3B5E),
    'blush': Color(0xFFE8CBC6),
    'sand': Color(0xFFDCCFB8),
  };

  static const envelopeNames = {'sage': 'Ada çayı', 'navy': 'Lacivert', 'blush': 'Pudra', 'sand': 'Kum'};

  static const sealColors = {
    'bordo': Color(0xFF8A1F3D),
    'gold': Color(0xFFA8833A),
    'ink': Color(0xFF1D2340),
    'forest': Color(0xFF2F5A45),
  };

  static const sealNames = {'bordo': 'Bordo', 'gold': 'Altın', 'ink': 'Mürekkep', 'forest': 'Orman'};

  static const flapFactor = 0.88;
  static const innerFactor = 0.62;

  static Color envelope(String key) => envelopeColors[key] ?? envelopeColors['sage']!;

  static Color seal(String key) => sealColors[key] ?? sealColors['bordo']!;

  /// Her kanalı çarpanla koyulaştırır; web'deki gibi en yakın tam sayıya yuvarlar.
  static Color scale(Color c, double factor) {
    int ch(double v) => (v * 255 * factor).round().clamp(0, 255);
    return Color.fromARGB(255, ch(c.r), ch(c.g), ch(c.b));
  }

  static Color hex(String value) => Color(int.parse(value.substring(1), radix: 16) | 0xFF000000);
}
