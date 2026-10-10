import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../events/data/events_repository.dart';
import '../data/pack_store.dart';
import '../data/purchase_controller.dart';

/// Mağaza ürünü: kullanılamıyorsa `null`.
final packProductProvider = FutureProvider.autoDispose<StoreProduct?>((ref) async {
  final store = ref.watch(packStoreProvider);
  if (!await store.isAvailable()) return null;
  return store.loadProduct();
});

/// M14 — Paywall: paket içeriği (§4), mağazadan gelen fiyat, "Satın al", bekleme ve hata durumları.
class PaywallScreen extends ConsumerStatefulWidget {
  const PaywallScreen({super.key, required this.eventId});

  final int eventId;

  @override
  ConsumerState<PaywallScreen> createState() => _PaywallScreenState();
}

class _PaywallScreenState extends ConsumerState<PaywallScreen> {
  @override
  void initState() {
    super.initState();
    // Önceki bir denemenin sonucu bu ekranı etkilemesin.
    Future.microtask(() {
      final s = ref.read(packPurchaseProvider);
      if (s is PackFailed || s is PackSucceeded) ref.read(packPurchaseProvider.notifier).reset();
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final product = ref.watch(packProductProvider);
    final purchase = ref.watch(packPurchaseProvider);
    final event = ref.watch(eventProvider(widget.eventId)).value;

    ref.listen(packPurchaseProvider, (_, next) {
      if (next is PackSucceeded && next.eventId == widget.eventId) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Etkinlik Paketi açıldı.')));
        ref.read(packPurchaseProvider.notifier).reset();
        if (context.canPop()) context.pop();
      }
    });

    final busy = purchase is PackBusy;

    return Scaffold(
      appBar: AppBar(title: const Text('Etkinlik Paketi')),
      body: ListView(
        padding: const EdgeInsets.all(AppSizes.pagePadding),
        children: [
          Text(
            event == null ? 'Bu etkinlik için sınırları kaldır' : '${event.title} için sınırları kaldır',
            style: theme.textTheme.headlineSmall,
          ),
          const SizedBox(height: 6),
          Text('Bir kez alınır, yalnız bu etkinlikte geçerlidir.', style: theme.textTheme.bodyMedium),
          const SizedBox(height: 20),
          const _Comparison(),
          const SizedBox(height: 24),
          if (event?.hasPack ?? false)
            const _Notice(text: 'Bu etkinlikte Etkinlik Paketi zaten açık.', success: true)
          else ...[
            switch (purchase) {
              PackPending() => const _Notice(
                key: Key('paywall-pending'),
                text: 'Ödemen onay bekliyor. Onaylanınca paket kendiliğinden açılacak.',
              ),
              PackFailed(:final message) => _Notice(key: const Key('paywall-error'), text: message),
              _ => const SizedBox.shrink(),
            },
            if (purchase is PackPending || purchase is PackFailed) const SizedBox(height: 16),
            switch (product) {
              AsyncData(value: final StoreProduct p) => PrimaryButton(
                key: const Key('buy-pack'),
                label: 'Satın al · ${p.price}',
                loading: busy,
                onPressed: purchase is PackPending
                    ? null
                    : () => ref.read(packPurchaseProvider.notifier).buy(widget.eventId, p),
              ),
              AsyncLoading() => const SizedBox(height: 52, child: LoadingState()),
              _ => Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const _Notice(key: Key('store-unavailable'), text: 'Mağazaya şu anda ulaşılamıyor.'),
                  const SizedBox(height: 12),
                  OutlinedButton(
                    onPressed: () => ref.invalidate(packProductProvider),
                    child: const Text('Tekrar dene'),
                  ),
                ],
              ),
            },
            if (busy)
              const Padding(
                padding: EdgeInsets.only(top: 12),
                child: Text('Satın alma sürüyor…', textAlign: TextAlign.center),
              ),
          ],
        ],
      ),
    );
  }
}

class _Comparison extends StatelessWidget {
  const _Comparison();

  static const rows = [
    ('Şablonlar', '4 ücretsiz', 'Tümü'),
    ('Davetli', '30', 'Sınırsız'),
    ('Zarf rengi', '2', '4'),
    ('Mühür rengi', '1', '4'),
  ];

  @override
  Widget build(BuildContext context) {
    const head = TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.inkSecondary);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.card),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        children: [
          const Row(
            children: [
              Expanded(flex: 3, child: SizedBox()),
              Expanded(
                flex: 2,
                child: Text('Ücretsiz', style: head, textAlign: TextAlign.center),
              ),
              Expanded(
                flex: 2,
                child: Text('Paket', style: head, textAlign: TextAlign.center),
              ),
            ],
          ),
          for (final (label, free, pack) in rows)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Row(
                children: [
                  Expanded(
                    flex: 3,
                    child: Text(
                      label,
                      style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.ink),
                    ),
                  ),
                  Expanded(
                    flex: 2,
                    child: Text(
                      free,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: AppColors.inkSecondary),
                    ),
                  ),
                  Expanded(
                    flex: 2,
                    child: Text(
                      pack,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: AppColors.accent, fontWeight: FontWeight.w700),
                    ),
                  ),
                ],
              ),
            ),
          const Padding(
            padding: EdgeInsets.only(top: 14),
            child: Text(
              'LCV takibi, web linki ve bildirimler iki sürümde de var.',
              style: TextStyle(fontSize: 13, color: AppColors.inkSecondary),
              textAlign: TextAlign.center,
            ),
          ),
        ],
      ),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({super.key, required this.text, this.success = false});

  final String text;
  final bool success;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: success ? AppColors.successSoft : AppColors.accentSoft,
        borderRadius: BorderRadius.circular(AppRadius.input),
      ),
      child: Text(
        text,
        style: TextStyle(color: success ? AppColors.success : AppColors.accent, fontWeight: FontWeight.w600),
      ),
    );
  }
}
