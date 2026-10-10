import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../data/envelope.dart';
import '../data/inbox_repository.dart';
import '../data/rsvp_draft.dart';

/// M8 LCV kartı: seçim, kişi sayacı, gönder; yanıt sonrası onay; süre dolduysa kilitli.
class RsvpSection extends ConsumerStatefulWidget {
  const RsvpSection({super.key, required this.detail});

  final EnvelopeDetail detail;

  @override
  ConsumerState<RsvpSection> createState() => _RsvpSectionState();
}

class _RsvpSectionState extends ConsumerState<RsvpSection> {
  late RsvpDraft _draft = RsvpDraft.from(widget.detail);

  /// Yanıt verilmişse önce özet gösterilir; "Değiştir" ile form açılır.
  late bool _editing = widget.detail.respondedAt == null;
  bool _sending = false;
  bool _justSent = false;
  String? _error;

  Future<void> _submit() async {
    final error = _draft.validate();
    setState(() => _error = error);
    if (error != null) return;

    setState(() => _sending = true);
    try {
      await ref.read(envelopeProvider(widget.detail.id).notifier).respond(_draft.status!, _draft.guestCount);
      if (mounted) {
        setState(() {
          _editing = false;
          _justSent = true;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final d = widget.detail;
    final theme = Theme.of(context);

    final Widget content;
    if (!d.rsvpOpen) {
      content = Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (d.respondedAt != null) _Summary(detail: d),
          Row(
            key: const Key('rsvp-locked'),
            children: [
              const Icon(Icons.lock_outline, size: 18, color: AppColors.inkSecondary),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  d.cancelled ? 'Etkinlik iptal edildiği için yanıt verilemez.' : 'LCV süresi doldu, yanıt verilemez.',
                  style: theme.textTheme.bodyMedium,
                ),
              ),
            ],
          ),
        ],
      );
    } else if (!_editing) {
      content = Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (_justSent)
            Container(
              key: const Key('rsvp-sent'),
              width: double.infinity,
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: AppColors.successSoft, borderRadius: BorderRadius.circular(AppRadius.input)),
              child: const Text('Yanıtın iletildi. Teşekkürler!', style: TextStyle(color: AppColors.success, fontWeight: FontWeight.w600)),
            ),
          _Summary(detail: d),
          TextButton(
            onPressed: () => setState(() {
              _editing = true;
              _justSent = false;
              _draft = RsvpDraft.from(d);
            }),
            child: const Text('Yanıtı değiştir'),
          ),
        ],
      );
    } else {
      content = Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: _Choice(label: 'Katılıyorum', selected: _draft.status == RsvpStatus.attending, onTap: () => _choose(RsvpStatus.attending))),
              const SizedBox(width: 10),
              Expanded(child: _Choice(label: 'Katılamıyorum', selected: _draft.status == RsvpStatus.notAttending, onTap: () => _choose(RsvpStatus.notAttending))),
            ],
          ),
          if (_draft.status == RsvpStatus.attending) ...[
            const SizedBox(height: 18),
            Text('Kaç kişi geleceksiniz?', style: theme.textTheme.bodyMedium),
            const SizedBox(height: 8),
            Row(
              children: [
                _StepButton(
                  key: const Key('guest-minus'),
                  icon: Icons.remove,
                  label: 'Azalt',
                  onPressed: _draft.canDecrement ? () => setState(() => _draft = _draft.decrement()) : null,
                ),
                SizedBox(
                  width: 72,
                  child: Text(
                    '${_draft.guestCount}',
                    key: const Key('guest-count'),
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w600),
                  ),
                ),
                _StepButton(
                  key: const Key('guest-plus'),
                  icon: Icons.add,
                  label: 'Artır',
                  onPressed: _draft.canIncrement ? () => setState(() => _draft = _draft.increment()) : null,
                ),
              ],
            ),
          ],
          ErrorText(_error),
          const SizedBox(height: 18),
          PrimaryButton(
            label: d.respondedAt == null ? 'Yanıtı gönder' : 'Yanıtı güncelle',
            loading: _sending,
            onPressed: _submit,
          ),
        ],
      );
    }

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.card),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Katılım durumun', style: theme.textTheme.titleLarge),
          const SizedBox(height: 14),
          content,
        ],
      ),
    );
  }

  void _choose(RsvpStatus status) => setState(() {
        _draft = _draft.copyWith(status: status);
        _error = null;
      });
}

class _Summary extends StatelessWidget {
  const _Summary({required this.detail});

  final EnvelopeDetail detail;

  @override
  Widget build(BuildContext context) {
    final text = switch (detail.rsvpStatus) {
      RsvpStatus.attending => 'Katılıyorum · ${detail.guestCount} kişi',
      RsvpStatus.notAttending => 'Katılamıyorum',
      RsvpStatus.pending => 'Henüz yanıt vermedin',
    };
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Text(text, key: const Key('rsvp-summary'), style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600)),
    );
  }
}

class _Choice extends StatelessWidget {
  const _Choice({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      selected: selected,
      button: true,
      child: Material(
        color: selected ? AppColors.accentSoft : AppColors.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.input),
          side: BorderSide(color: selected ? AppColors.accent : AppColors.border, width: selected ? 1.5 : 1),
        ),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(AppRadius.input),
          child: SizedBox(
            height: 52,
            child: Center(
              child: Text(
                label,
                style: TextStyle(fontWeight: FontWeight.w600, color: selected ? AppColors.accent : AppColors.ink),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _StepButton extends StatelessWidget {
  const _StepButton({super.key, required this.icon, required this.label, required this.onPressed});

  final IconData icon;
  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return IconButton.outlined(
      onPressed: onPressed,
      tooltip: label,
      icon: Icon(icon),
      style: IconButton.styleFrom(
        minimumSize: const Size(48, 48),
        side: const BorderSide(color: AppColors.border),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.input)),
      ),
    );
  }
}
