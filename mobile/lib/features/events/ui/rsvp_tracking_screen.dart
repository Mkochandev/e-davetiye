import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/format.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../inbox/data/envelope.dart' show RsvpStatus;
import '../data/events_repository.dart';
import '../data/invitation.dart';
import 'event_detail_screen.dart' show StatsGrid;
import 'send_screen.dart' show WhatsAppButton;

enum RsvpFilter {
  all('Tümü'),
  attending('Katılıyor'),
  notAttending('Katılamıyor'),
  pending('Bekliyor'),
  unopened('Açılmadı');

  const RsvpFilter(this.label);
  final String label;

  /// Kovalar sunucu istatistiğiyle aynı: açılmadı → açıldı ama yanıt yok (bekliyor) → yanıt verdi.
  bool matches(Recipient r) => switch (this) {
    all => true,
    attending => r.rsvpStatus == RsvpStatus.attending,
    notAttending => r.rsvpStatus == RsvpStatus.notAttending,
    pending => r.isOpened && r.rsvpStatus == RsvpStatus.pending,
    unopened => !r.isOpened && r.rsvpStatus == RsvpStatus.pending,
  };
}

/// M16 — LCV takibi: sayaçlar, filtreli liste, link alıcısına WhatsApp hatırlatma.
class RsvpTrackingScreen extends ConsumerStatefulWidget {
  const RsvpTrackingScreen({super.key, required this.eventId});

  final int eventId;

  @override
  ConsumerState<RsvpTrackingScreen> createState() => _RsvpTrackingScreenState();
}

class _RsvpTrackingScreenState extends ConsumerState<RsvpTrackingScreen> {
  RsvpFilter _filter = RsvpFilter.all;

  @override
  Widget build(BuildContext context) {
    final stats = ref.watch(statsProvider(widget.eventId));
    final recipients = ref.watch(recipientsProvider(widget.eventId));
    final error = stats.error ?? recipients.error;

    Future<void> refresh() async {
      ref.invalidate(statsProvider(widget.eventId));
      ref.invalidate(recipientsProvider(widget.eventId));
    }

    return Scaffold(
      appBar: AppBar(title: const Text('LCV takibi')),
      body: switch ((stats.value, recipients.value)) {
        _ when error != null => ErrorState(message: ApiException.from(error).message, onRetry: refresh),
        (final RsvpStats s, final List<Recipient> list) => RefreshIndicator(
          color: AppColors.accent,
          onRefresh: refresh,
          child: _list(s, list.where((r) => r.isSent).toList()),
        ),
        _ => const LoadingState(),
      },
    );
  }

  Widget _list(RsvpStats stats, List<Recipient> sent) {
    final visible = sent.where(_filter.matches).toList();

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      children: [
        StatsGrid(stats: stats),
        const SizedBox(height: 16),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: [
              for (final f in RsvpFilter.values)
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    key: Key('filter-${f.name}'),
                    label: Text('${f.label} (${sent.where(f.matches).length})'),
                    selected: _filter == f,
                    onSelected: (_) => setState(() => _filter = f),
                    showCheckmark: false,
                    selectedColor: AppColors.accentSoft,
                    labelStyle: TextStyle(
                      fontWeight: FontWeight.w600,
                      color: _filter == f ? AppColors.accent : AppColors.ink,
                    ),
                    side: BorderSide(color: _filter == f ? AppColors.accent : AppColors.border),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        if (visible.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 32),
            child: Text(
              'Bu filtrede kimse yok.',
              textAlign: TextAlign.center,
              style: TextStyle(color: AppColors.inkSecondary),
            ),
          ),
        for (final r in visible) _RecipientRow(recipient: r),
      ],
    );
  }
}

class _RecipientRow extends StatelessWidget {
  const _RecipientRow({required this.recipient});

  final Recipient recipient;

  @override
  Widget build(BuildContext context) {
    final r = recipient;
    final (status, color) = switch (r) {
      Recipient(rsvpStatus: RsvpStatus.attending) => ('Katılıyor · ${r.guestCount} kişi', AppColors.success),
      Recipient(rsvpStatus: RsvpStatus.notAttending) => ('Katılamıyor', AppColors.inkSecondary),
      Recipient(isOpened: true) => ('Açtı, yanıt bekleniyor', AppColors.inkSecondary),
      _ => ('Açmadı', AppColors.accent),
    };
    // Yanıt vermemiş link alıcısına hatırlatma (spec §10 M16).
    final canRemind = r.channel == RecipientChannel.link && r.whatsappUrl != null && r.rsvpStatus == RsvpStatus.pending;

    return Container(
      key: Key('rsvp-row-${r.id}'),
      padding: const EdgeInsets.symmetric(vertical: 12),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: AppColors.border)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  r.name?.isNotEmpty ?? false ? r.name! : AppFormat.nationalPhone(r.phone),
                  style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.ink),
                ),
                const SizedBox(height: 2),
                Text(
                  status,
                  style: TextStyle(color: color, fontSize: 13, fontWeight: FontWeight.w500),
                ),
              ],
            ),
          ),
          if (canRemind)
            SizedBox(
              width: 132,
              child: WhatsAppButton(url: r.whatsappUrl!, label: 'Hatırlat'),
            ),
        ],
      ),
    );
  }
}
