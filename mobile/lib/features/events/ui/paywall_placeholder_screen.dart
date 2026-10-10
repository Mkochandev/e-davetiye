import 'package:flutter/material.dart';

import '../../../core/theme/tokens.dart';

/// M14 yer tutucu: gerçek satın alma Adım 8'de. Paket içeriği spec §4.
class PaywallPlaceholderScreen extends StatelessWidget {
  const PaywallPlaceholderScreen({super.key, required this.eventId});

  final int eventId;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Etkinlik Paketi')),
      body: ListView(
        padding: const EdgeInsets.all(AppSizes.pagePadding),
        children: [
          Text('Bu etkinlik için sınırları kaldır', style: theme.textTheme.headlineSmall),
          const SizedBox(height: 16),
          for (final line in const [
            'Tüm şablonlar (premium dahil)',
            'Sınırsız davetli',
            '4 zarf rengi ve 4 mühür rengi',
          ])
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Row(
                children: [
                  const Icon(Icons.check_circle, color: AppColors.success, size: 20),
                  const SizedBox(width: 10),
                  Expanded(child: Text(line, style: theme.textTheme.bodyLarge)),
                ],
              ),
            ),
          const SizedBox(height: 20),
          Container(
            key: const Key('paywall-placeholder'),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.accentSoft,
              borderRadius: BorderRadius.circular(AppRadius.input),
            ),
            child: const Text(
              'Satın alma çok yakında.',
              style: TextStyle(color: AppColors.accent, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}
