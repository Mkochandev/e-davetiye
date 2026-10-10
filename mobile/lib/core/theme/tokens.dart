import 'dart:ui';

/// Tasarım tokenları (spec §15). Web `/z` sayfası aynı değerleri kullanır.
abstract final class AppColors {
  static const background = Color(0xFFF2F3F5);
  static const surface = Color(0xFFFFFFFF);
  static const ink = Color(0xFF17182A);
  static const inkSecondary = Color(0xFF5F6273);
  static const border = Color(0xFFDDDFE6);
  static const accent = Color(0xFF8A1F3D);
  static const accentSoft = Color(0xFFF6E7EC);
  static const success = Color(0xFF23603F);
  static const successSoft = Color(0xFFE3F0E8);

  /// Zarf açma ekranı zemini.
  static const stage = Color(0xFF17182A);
}

abstract final class AppRadius {
  static const card = 22.0;
  static const button = 16.0;
  static const input = 14.0;
}

abstract final class AppSizes {
  /// En küçük dokunma alanı.
  static const minTap = 44.0;
  static const pagePadding = 20.0;
}
