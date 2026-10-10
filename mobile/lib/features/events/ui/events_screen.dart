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

/// M9 — Etkinliklerim: salondan gelenler rozetli, kendi etkinlikleri, "Yeni etkinlik".
class EventsScreen extends ConsumerWidget {
  const EventsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final events = ref.watch(eventsProvider);
    Future<void> refresh() => ref.refresh(eventsProvider.future);

    return Scaffold(
      appBar: AppBar(title: const Text('Etkinliklerim')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push(Routes.newEvent),
        backgroundColor: AppColors.accent,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add),
        label: const Text('Yeni etkinlik', style: TextStyle(fontWeight: FontWeight.w600)),
      ),
      body: switch (events) {
        AsyncData(:final value) when value.isEmpty => const EmptyState(
          icon: Icons.event_outlined,
          title: 'Henüz etkinliğin yok',
          message: 'Salonunun eklediği ya da senin oluşturduğun etkinlikler burada görünecek.',
        ),
        AsyncData(:final value) => RefreshIndicator(
          onRefresh: refresh,
          color: AppColors.accent,
          child: ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
            itemCount: value.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (_, i) => _EventTile(event: value[i]),
          ),
        ),
        AsyncError(:final error) => ErrorState(message: ApiException.from(error).message, onRetry: refresh),
        _ => const LoadingState(),
      },
    );
  }
}

class _EventTile extends StatelessWidget {
  const _EventTile({required this.event});

  final AppEvent event;

  @override
  Widget build(BuildContext context) {
    final e = event;
    final theme = Theme.of(context);

    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.card),
      child: InkWell(
        key: Key('event-${e.id}'),
        borderRadius: BorderRadius.circular(AppRadius.card),
        onTap: () => context.push(Routes.eventFor(e.id)),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Wrap(
                spacing: 8,
                runSpacing: 6,
                children: [
                  Pill(label: e.type.label),
                  if (e.fromSalon) const Pill(label: 'Salon tarafından eklendi', accent: true),
                  if (e.cancelled) const Pill(label: 'İptal edildi', accent: true),
                ],
              ),
              const SizedBox(height: 10),
              Text(e.title, style: theme.textTheme.titleLarge),
              const SizedBox(height: 4),
              Text(AppFormat.eventDateTime(e.startsAt), style: theme.textTheme.bodyMedium),
              Text(e.venueName, style: theme.textTheme.bodyMedium),
              const SizedBox(height: 10),
              Text(
                switch (e.invitationStatus) {
                  InvitationStatus.none => 'Davetiye henüz yok',
                  InvitationStatus.draft => 'Davetiye taslak',
                  InvitationStatus.sent => 'Davetiye gönderildi',
                },
                style: TextStyle(
                  fontWeight: FontWeight.w600,
                  fontSize: 14,
                  color: e.invitationStatus == InvitationStatus.sent ? AppColors.success : AppColors.inkSecondary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
