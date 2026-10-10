import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/format.dart';
import '../../../core/invitation/invitation_card.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../data/envelope.dart';
import '../data/inbox_repository.dart';
import '../data/invitation_actions.dart';
import 'rsvp_section.dart';

/// M8 — Davetiye + LCV.
class InvitationScreen extends ConsumerWidget {
  const InvitationScreen({super.key, required this.id});

  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(envelopeProvider(id));

    return Scaffold(
      appBar: AppBar(title: const Text('Davetiye')),
      body: switch (detail) {
        AsyncData(:final value) => _Body(detail: value),
        AsyncError(:final error) => ErrorState(
            message: ApiException.from(error).message,
            onRetry: () => ref.invalidate(envelopeProvider(id)),
          ),
        _ => const LoadingState(),
      },
    );
  }
}

class _Body extends StatelessWidget {
  const _Body({required this.detail});

  final EnvelopeDetail detail;

  Future<void> _run(BuildContext context, Future<bool> Function() action, String failure) async {
    final messenger = ScaffoldMessenger.of(context);
    var ok = false;
    try {
      ok = await action();
    } catch (_) {
      ok = false;
    }
    if (!ok) messenger.showSnackBar(SnackBar(content: Text(failure)));
  }

  @override
  Widget build(BuildContext context) {
    final d = detail;

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
      children: [
        if (d.cancelled)
          Container(
            key: const Key('cancelled-banner'),
            margin: const EdgeInsets.only(bottom: 16),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(color: AppColors.accentSoft, borderRadius: BorderRadius.circular(AppRadius.input)),
            child: const Text('Bu etkinlik iptal edildi', style: TextStyle(color: AppColors.accent, fontWeight: FontWeight.w600)),
          ),
        InvitationCard(
          config: d.config,
          texts: d.texts,
          startsAt: d.startsAt,
          venueName: d.venueName,
          venueAddress: d.venueAddress,
        ),
        if (!d.cancelled) ...[
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _ActionButton(
                  icon: Icons.event_available_outlined,
                  label: 'Takvime ekle',
                  onPressed: () => _run(context, () => InvitationActions.addToCalendar(d), 'Takvim açılamadı.'),
                ),
              ),
              if (d.hasLocation) ...[
                const SizedBox(width: 10),
                Expanded(
                  child: _ActionButton(
                    icon: Icons.directions_outlined,
                    label: 'Yol tarifi',
                    onPressed: () => _run(context, () => InvitationActions.openDirections(d), 'Harita açılamadı.'),
                  ),
                ),
              ],
            ],
          ),
        ],
        const SizedBox(height: 16),
        RsvpSection(detail: d),
        if (d.rsvpDeadline != null && d.rsvpOpen)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Text(
              'Son yanıt tarihi: ${AppFormat.calendarDate(d.rsvpDeadline!)}',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ),
      ],
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({required this.icon, required this.label, required this.onPressed});

  final IconData icon;
  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton.icon(
      onPressed: onPressed,
      icon: Icon(icon, size: 20),
      label: Text(label),
      style: OutlinedButton.styleFrom(
        foregroundColor: AppColors.ink,
        backgroundColor: AppColors.surface,
        side: const BorderSide(color: AppColors.border),
        minimumSize: const Size.fromHeight(52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.button)),
        textStyle: const TextStyle(fontFamily: AppFonts.body, fontSize: 15, fontWeight: FontWeight.w600),
      ),
    );
  }
}
