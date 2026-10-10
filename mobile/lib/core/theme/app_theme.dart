import 'package:flutter/material.dart';

import 'tokens.dart';

abstract final class AppFonts {
  static const body = 'Plus Jakarta Sans';
  static const display = 'Fraunces';

  /// Fraunces değişken font olarak gömülü; ağırlık `wght` ekseniyle de verilmeli.
  static TextStyle displayStyle({
    required double size,
    FontWeight weight = FontWeight.w500,
    Color? color,
    double? height,
  }) {
    return TextStyle(
      fontFamily: display,
      fontSize: size,
      fontWeight: weight,
      fontVariations: [FontVariation.weight(weight.value.toDouble())],
      color: color ?? AppColors.ink,
      height: height,
    );
  }
}

abstract final class AppTheme {
  static ThemeData light() {
    const scheme = ColorScheme(
      brightness: Brightness.light,
      primary: AppColors.accent,
      onPrimary: Colors.white,
      primaryContainer: AppColors.accentSoft,
      onPrimaryContainer: AppColors.accent,
      secondary: AppColors.ink,
      onSecondary: Colors.white,
      error: AppColors.accent,
      onError: Colors.white,
      surface: AppColors.surface,
      onSurface: AppColors.ink,
      onSurfaceVariant: AppColors.inkSecondary,
      outline: AppColors.border,
      outlineVariant: AppColors.border,
    );

    final base = ThemeData(useMaterial3: true, colorScheme: scheme, fontFamily: AppFonts.body);
    final text = base.textTheme.apply(bodyColor: AppColors.ink, displayColor: AppColors.ink);

    final inputBorder = OutlineInputBorder(
      borderRadius: BorderRadius.circular(AppRadius.input),
      borderSide: const BorderSide(color: AppColors.border),
    );

    return base.copyWith(
      scaffoldBackgroundColor: AppColors.background,
      textTheme: text.copyWith(
        headlineMedium: AppFonts.displayStyle(size: 28, weight: FontWeight.w600, height: 1.2),
        headlineSmall: AppFonts.displayStyle(size: 22, weight: FontWeight.w600, height: 1.25),
        titleLarge: AppFonts.displayStyle(size: 20, weight: FontWeight.w600),
        titleMedium: text.titleMedium?.copyWith(fontWeight: FontWeight.w600),
        bodyLarge: text.bodyLarge?.copyWith(fontSize: 16, height: 1.5),
        bodyMedium: text.bodyMedium?.copyWith(fontSize: 14, height: 1.5, color: AppColors.inkSecondary),
        labelLarge: text.labelLarge?.copyWith(fontSize: 16, fontWeight: FontWeight.w600),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: AppColors.background,
        foregroundColor: AppColors.ink,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: false,
        titleTextStyle: AppFonts.displayStyle(size: 22, weight: FontWeight.w600),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surface,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        border: inputBorder,
        enabledBorder: inputBorder,
        focusedBorder: inputBorder.copyWith(borderSide: const BorderSide(color: AppColors.ink, width: 1.5)),
        errorBorder: inputBorder.copyWith(borderSide: const BorderSide(color: AppColors.accent)),
        focusedErrorBorder: inputBorder.copyWith(borderSide: const BorderSide(color: AppColors.accent, width: 1.5)),
        hintStyle: const TextStyle(color: AppColors.inkSecondary),
        errorStyle: const TextStyle(color: AppColors.accent, fontSize: 13),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.accent,
          foregroundColor: Colors.white,
          disabledBackgroundColor: AppColors.border,
          disabledForegroundColor: AppColors.inkSecondary,
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.button)),
          textStyle: const TextStyle(fontFamily: AppFonts.body, fontSize: 16, fontWeight: FontWeight.w600),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.accent,
          minimumSize: const Size(AppSizes.minTap, AppSizes.minTap),
          textStyle: const TextStyle(fontFamily: AppFonts.body, fontSize: 15, fontWeight: FontWeight.w600),
        ),
      ),
      checkboxTheme: CheckboxThemeData(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
        side: const BorderSide(color: AppColors.inkSecondary, width: 1.5),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: AppColors.surface,
        indicatorColor: AppColors.accentSoft,
        surfaceTintColor: Colors.transparent,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => TextStyle(
            fontFamily: AppFonts.body,
            fontSize: 12,
            fontWeight: states.contains(WidgetState.selected) ? FontWeight.w600 : FontWeight.w500,
            color: states.contains(WidgetState.selected) ? AppColors.accent : AppColors.inkSecondary,
          ),
        ),
        iconTheme: WidgetStateProperty.resolveWith(
          (states) =>
              IconThemeData(color: states.contains(WidgetState.selected) ? AppColors.accent : AppColors.inkSecondary),
        ),
      ),
      snackBarTheme: const SnackBarThemeData(behavior: SnackBarBehavior.floating),
    );
  }
}
