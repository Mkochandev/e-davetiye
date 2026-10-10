import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/router/router.dart';
import 'core/theme/app_theme.dart';
import 'features/purchase/data/purchase_controller.dart';

class ZarfApp extends ConsumerWidget {
  const ZarfApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // Satın alma güncellemeleri açılıştan itibaren dinlenir (bekleyen satın almalar dahil); durum değişince
    // uygulama yeniden çizilmez.
    ref.watch(packPurchaseProvider.select((_) => null));

    return MaterialApp.router(
      title: 'Zarf',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      routerConfig: ref.watch(routerProvider),
      locale: const Locale('tr', 'TR'),
      supportedLocales: const [Locale('tr', 'TR')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
    );
  }
}
