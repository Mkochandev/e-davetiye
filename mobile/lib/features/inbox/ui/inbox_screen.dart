import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/format.dart';
import '../../../core/invitation/envelope_view.dart';
import '../../../core/router/router.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../data/envelope.dart';
import '../data/inbox_repository.dart';

/// M6 — Posta kutusu: "Açılmamış" ve "Açılmış" bölümleri, çekerek yenileme.
class InboxScreen extends ConsumerWidget {
  const InboxScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final inbox = ref.watch(inboxProvider);
    Future<void> refresh() => ref.read(inboxProvider.notifier).refresh();

    return Scaffold(
      appBar: AppBar(title: const Text('Posta kutusu')),
      body: switch (inbox) {
        AsyncData(:final value) => RefreshIndicator(
            onRefresh: refresh,
            color: AppColors.accent,
            child: value.isEmpty ? const _EmptyInbox() : _EnvelopeList(envelopes: value),
          ),
        AsyncError(:final error) => ErrorState(message: ApiException.from(error).message, onRetry: refresh),
        _ => const LoadingState(),
      },
    );
  }
}

class _EmptyInbox extends StatelessWidget {
  const _EmptyInbox();

  @override
  Widget build(BuildContext context) {
    // Boşken de çekerek yenilenebilsin.
    return LayoutBuilder(
      builder: (context, constraints) => SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        child: SizedBox(
          height: constraints.maxHeight,
          child: const EmptyState(
            icon: Icons.mail_outline,
            title: 'Posta kutun boş',
            message: 'Sana gönderilen davetiyeler burada zarf olarak görünecek.',
          ),
        ),
      ),
    );
  }
}

class _EnvelopeList extends StatelessWidget {
  const _EnvelopeList({required this.envelopes});

  final List<Envelope> envelopes;

  @override
  Widget build(BuildContext context) {
    final unopened = envelopes.where((e) => !e.isOpened).toList();
    final opened = envelopes.where((e) => e.isOpened).toList();

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
      children: [
        if (unopened.isNotEmpty) ...[
          const _SectionTitle('Açılmamış'),
          for (final e in unopened) _EnvelopeTile(envelope: e),
        ],
        if (opened.isNotEmpty) ...[
          const _SectionTitle('Açılmış'),
          for (final e in opened) _EnvelopeTile(envelope: e),
        ],
      ],
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 16, 4, 10),
      child: Text(
        text.toUpperCase(),
        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, letterSpacing: 1.2, color: AppColors.inkSecondary),
      ),
    );
  }
}

class _EnvelopeTile extends StatelessWidget {
  const _EnvelopeTile({required this.envelope});

  final Envelope envelope;

  @override
  Widget build(BuildContext context) {
    final e = envelope;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Material(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.card),
        child: InkWell(
          key: Key('envelope-${e.id}'),
          borderRadius: BorderRadius.circular(AppRadius.card),
          // Açılmamış zarf önce açılır (M7); açılmış olan doğrudan davetiyeye gider (M8).
          onTap: () => context.push(e.isOpened ? Routes.invitationFor(e.id) : Routes.envelopeFor(e.id)),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              children: [
                EnvelopeView(color: e.envelopeColor, sealColor: e.sealColor, sealText: e.sealText, width: 96),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(e.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontSize: 18)),
                      const SizedBox(height: 4),
                      Text(AppFormat.eventDateTime(e.startsAt), style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontSize: 13)),
                      const SizedBox(height: 8),
                      _StatusChip(envelope: e),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.envelope});

  final Envelope envelope;

  @override
  Widget build(BuildContext context) {
    final (label, fg, bg) = switch (envelope) {
      Envelope(cancelled: true) => ('İptal edildi', AppColors.accent, AppColors.accentSoft),
      Envelope(isOpened: false) => ('Yeni', AppColors.accent, AppColors.accentSoft),
      Envelope(rsvpStatus: RsvpStatus.attending) => ('Katılıyorsun', AppColors.success, AppColors.successSoft),
      Envelope(rsvpStatus: RsvpStatus.notAttending) => ('Katılmıyorsun', AppColors.inkSecondary, AppColors.background),
      _ => ('Yanıt bekleniyor', AppColors.inkSecondary, AppColors.background),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Text(label, style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w600)),
    );
  }
}
