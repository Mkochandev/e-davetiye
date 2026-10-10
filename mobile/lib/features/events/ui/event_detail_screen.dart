import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/format.dart';
import '../../../core/router/router.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../data/event.dart';
import '../data/events_repository.dart';
import '../data/invitation.dart';

/// M11 — Etkinlik detayı: durum kartı, düzenleme, LCV özeti, salonla paylaşım anahtarı.
class EventDetailScreen extends ConsumerWidget {
  const EventDetailScreen({super.key, required this.id});

  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final event = ref.watch(eventProvider(id));

    return Scaffold(
      appBar: AppBar(title: const Text('Etkinlik')),
      body: switch (event) {
        AsyncData(:final value) => RefreshIndicator(
          color: AppColors.accent,
          onRefresh: () async => refreshEvent(ref, id),
          child: _Body(event: value),
        ),
        AsyncError(:final error) => ErrorState(
          message: ApiException.from(error).message,
          onRetry: () => ref.invalidate(eventProvider(id)),
        ),
        _ => const LoadingState(),
      },
    );
  }
}

class _Body extends ConsumerWidget {
  const _Body({required this.event});

  final AppEvent event;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final e = event;
    final theme = Theme.of(context);

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
      children: [
        if (e.cancelled)
          Container(
            margin: const EdgeInsets.only(bottom: 12),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.accentSoft,
              borderRadius: BorderRadius.circular(AppRadius.input),
            ),
            child: const Text(
              'Bu etkinlik salon tarafından iptal edildi. Gönderilen davetiyeler alıcılara "iptal edildi" olarak görünür.',
              style: TextStyle(color: AppColors.accent, fontWeight: FontWeight.w600),
            ),
          ),
        _Card(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Wrap(
                spacing: 8,
                runSpacing: 6,
                children: [
                  Pill(label: e.type.label),
                  if (e.fromSalon) const Pill(label: 'Salon tarafından eklendi', accent: true),
                  if (e.hasPack) const Pill(label: 'Etkinlik Paketi', success: true),
                ],
              ),
              const SizedBox(height: 12),
              Text(e.title, style: theme.textTheme.headlineSmall),
              const SizedBox(height: 8),
              _InfoRow(icon: Icons.schedule, text: AppFormat.eventDateTime(e.startsAt)),
              _InfoRow(
                icon: Icons.place_outlined,
                text: [e.venueName, e.venueAddress].whereType<String>().where((s) => s.isNotEmpty).join(' · '),
              ),
              if (e.fromSalon)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text('Tarih, saat ve mekanı ${e.salonName} günceller.', style: theme.textTheme.bodyMedium),
                ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        _InvitationCard(event: e),
        if (e.fromSalon) ...[
          const SizedBox(height: 12),
          _Card(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            child: SwitchListTile(
              key: const Key('headcount-switch'),
              value: e.shareHeadcountWithSalon,
              activeThumbColor: AppColors.accent,
              title: const Text('Kişi sayısını salonla paylaş', style: TextStyle(fontWeight: FontWeight.w600)),
              subtitle: const Text('Salon, hazırlık için katılım sayılarını görür; kimin geldiğini görmez.'),
              onChanged: (share) async {
                final messenger = ScaffoldMessenger.of(context);
                try {
                  await ref.read(eventsRepositoryProvider).setHeadcountSharing(e.id, share);
                  ref.invalidate(eventProvider(e.id));
                } on ApiException catch (err) {
                  messenger.showSnackBar(SnackBar(content: Text(err.message)));
                }
              },
            ),
          ),
        ],
      ],
    );
  }
}

/// Durum kartı: davetiye yok / taslak / gönderildi.
class _InvitationCard extends ConsumerWidget {
  const _InvitationCard({required this.event});

  final AppEvent event;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final e = event;
    final theme = Theme.of(context);
    final status = e.invitationStatus;

    final (title, text) = switch (status) {
      InvitationStatus.none => ('Davetiye henüz yok', 'Şablon seç, metinleri düzenle, zarfını hazırla.'),
      InvitationStatus.draft => ('Davetiye taslak', 'Davetlilerini ekle ve gönder.'),
      InvitationStatus.sent => ('Davetiye gönderildi', 'Yanıtları buradan takip et.'),
    };

    return _Card(
      key: const Key('invitation-status'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(title, style: theme.textTheme.titleLarge),
          const SizedBox(height: 4),
          Text(text, style: theme.textTheme.bodyMedium),
          if (status == InvitationStatus.sent) ...[const SizedBox(height: 16), _StatsSummary(eventId: e.id)],
          const SizedBox(height: 16),
          if (status == InvitationStatus.none)
            FilledButton(
              onPressed: e.cancelled ? null : () => context.push(Routes.editorFor(e.id)),
              child: const Text('Davetiye oluştur'),
            )
          else ...[
            if (!e.cancelled)
              FilledButton(
                onPressed: () => context.push(Routes.sendFor(e.id)),
                child: Text(status == InvitationStatus.sent ? 'Yeni davetlileri gönder' : 'Gönder'),
              ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: _SecondaryButton(
                    label: 'Davetliler',
                    onPressed: () => context.push(Routes.recipientsFor(e.id)),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _SecondaryButton(
                    label: 'Düzenle',
                    onPressed: e.cancelled ? null : () => context.push(Routes.editorFor(e.id)),
                  ),
                ),
              ],
            ),
            if (status == InvitationStatus.sent) ...[
              const SizedBox(height: 8),
              _SecondaryButton(label: 'LCV takibi', onPressed: () => context.push(Routes.rsvpFor(e.id))),
            ],
          ],
        ],
      ),
    );
  }
}

class _StatsSummary extends ConsumerWidget {
  const _StatsSummary({required this.eventId});

  final int eventId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final stats = ref.watch(statsProvider(eventId));
    return switch (stats) {
      AsyncData(:final value) => StatsGrid(stats: value),
      AsyncError() => const Text('LCV özeti yüklenemedi.'),
      _ => const SizedBox(height: 64, child: LoadingState()),
    };
  }
}

/// LCV sayaçları (M11 özet ve M16).
class StatsGrid extends StatelessWidget {
  const StatsGrid({super.key, required this.stats});

  final RsvpStats stats;

  @override
  Widget build(BuildContext context) {
    final items = [
      ('Katılıyor', '${stats.attending}', '${stats.attendingGuests} kişi'),
      ('Katılamıyor', '${stats.notAttending}', null),
      ('Bekliyor', '${stats.pending}', null),
      ('Açılmadı', '${stats.unopened}', null),
    ];
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final (label, value, sub) in items)
            Expanded(
              child: Container(
                margin: const EdgeInsets.symmetric(horizontal: 3),
                padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
                decoration: BoxDecoration(
                  color: AppColors.background,
                  borderRadius: BorderRadius.circular(AppRadius.input),
                ),
                child: Column(
                  children: [
                    Text(
                      value,
                      style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: AppColors.ink),
                    ),
                    Text(
                      label,
                      style: const TextStyle(fontSize: 12, color: AppColors.inkSecondary),
                      textAlign: TextAlign.center,
                    ),
                    if (sub != null)
                      Text(
                        sub,
                        style: const TextStyle(fontSize: 12, color: AppColors.success, fontWeight: FontWeight.w600),
                      ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _Card extends StatelessWidget {
  const _Card({super.key, required this.child, this.padding = const EdgeInsets.all(18)});

  final Widget child;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    // Material: içindeki SwitchListTile'ın arka planı ve dokunma efekti görünsün.
    return Material(
      color: AppColors.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.card),
        side: const BorderSide(color: AppColors.border),
      ),
      clipBehavior: Clip.antiAlias,
      child: Padding(padding: padding, child: child),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: AppColors.inkSecondary),
          const SizedBox(width: 8),
          Expanded(child: Text(text, style: const TextStyle(fontSize: 15))),
        ],
      ),
    );
  }
}

class _SecondaryButton extends StatelessWidget {
  const _SecondaryButton({required this.label, required this.onPressed});

  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton(
      onPressed: onPressed,
      style: OutlinedButton.styleFrom(
        foregroundColor: AppColors.ink,
        side: const BorderSide(color: AppColors.border),
        minimumSize: const Size.fromHeight(48),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.button)),
      ),
      child: Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
    );
  }
}
