import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/format.dart';
import '../../../core/invitation/envelope_view.dart';
import '../../../core/invitation/invitation_card.dart';
import '../../../core/invitation/invitation_data.dart';
import '../../../core/invitation/palette.dart';
import '../../../core/router/router.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../data/event.dart';
import '../data/events_repository.dart';
import '../data/invitation.dart';

/// M12 — Davetiye düzenleyici: şablon → metinler → zarf; her adımda canlı önizleme.
class InvitationEditorScreen extends ConsumerWidget {
  const InvitationEditorScreen({super.key, required this.eventId});

  final int eventId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final event = ref.watch(eventProvider(eventId));
    final invitation = ref.watch(invitationProvider(eventId));
    final type = event.value?.type;
    final templates = type == null ? null : ref.watch(templatesProvider(type));

    final error = event.error ?? invitation.error ?? templates?.error;
    if (error != null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Davetiye')),
        body: ErrorState(
          message: ApiException.from(error).message,
          onRetry: () {
            ref.invalidate(eventProvider(eventId));
            ref.invalidate(invitationProvider(eventId));
            if (type != null) ref.invalidate(templatesProvider(type));
          },
        ),
      );
    }

    if (event.value == null || !invitation.hasValue || templates?.value == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Davetiye')),
        body: const LoadingState(),
      );
    }

    return _Editor(event: event.value!, initial: invitation.value, templates: templates!.value!);
  }
}

class _Editor extends ConsumerStatefulWidget {
  const _Editor({required this.event, required this.initial, required this.templates});

  final AppEvent event;
  final InvitationDraft? initial;
  final List<Template> templates;

  @override
  ConsumerState<_Editor> createState() => _EditorState();
}

class _EditorState extends ConsumerState<_Editor> {
  static const steps = ['Şablon', 'Metinler', 'Zarf'];

  late InvitationDraft _draft;
  int _step = 0;
  bool _saving = false;
  String? _error;

  late final _label = TextEditingController(text: _draft.texts.label);
  late final _intro = TextEditingController(text: _draft.texts.intro);
  late final _names = TextEditingController(text: _draft.texts.names);
  late final _message = TextEditingController(text: _draft.texts.message);
  late final _seal = TextEditingController(text: _draft.sealText);

  @override
  void initState() {
    super.initState();
    final initial = widget.initial ?? InvitationDraft.defaults(widget.event);
    final firstFree = widget.templates.where((t) => !t.isPremium).firstOrNull ?? widget.templates.firstOrNull;
    _draft = initial.templateId == null ? initial.copyWith(templateId: firstFree?.id) : initial;
  }

  @override
  void dispose() {
    for (final c in [_label, _intro, _names, _message, _seal]) {
      c.dispose();
    }
    super.dispose();
  }

  bool get _hasPack => widget.event.hasPack;

  Template? get _template => widget.templates.where((t) => t.id == _draft.templateId).firstOrNull;

  void _syncTexts() {
    setState(
      () => _draft = _draft.copyWith(
        texts: InvitationTexts(label: _label.text, intro: _intro.text, names: _names.text, message: _message.text),
        sealText: _seal.text,
      ),
    );
  }

  void _openPaywall() => context.push(Routes.paywallFor(widget.event.id));

  Future<void> _save() async {
    _syncTexts();
    if (_draft.texts.names.trim().isEmpty) {
      setState(() {
        _step = 1;
        _error = 'İsimler alanı boş olamaz.';
      });
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ref.read(eventsRepositoryProvider).saveInvitation(widget.event.id, _draft);
      refreshEvent(ref, widget.event.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Davetiye kaydedildi.')));
      context.pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.isPackRequired) {
        _openPaywall();
      } else {
        setState(() => _error = e.message);
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.initial == null ? 'Davetiye oluştur' : 'Davetiyeyi düzenle')),
      body: Column(
        children: [
          _StepIndicator(steps: steps, current: _step, onTap: (i) => setState(() => _step = i)),
          Expanded(
            child: ListView(
              key: ValueKey('step-$_step'),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                _Preview(step: _step, draft: _draft, template: _template, event: widget.event),
                const SizedBox(height: 20),
                ...switch (_step) {
                  0 => _templateStep(),
                  1 => _textsStep(),
                  _ => _envelopeStep(),
                },
                ErrorText(_error),
              ],
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
              child: Row(
                children: [
                  if (_step > 0)
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => setState(() => _step--),
                        style: OutlinedButton.styleFrom(
                          minimumSize: const Size.fromHeight(52),
                          foregroundColor: AppColors.ink,
                          side: const BorderSide(color: AppColors.border),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.button)),
                        ),
                        child: const Text('Geri'),
                      ),
                    ),
                  if (_step > 0) const SizedBox(width: 10),
                  Expanded(
                    flex: 2,
                    child: _step < steps.length - 1
                        ? FilledButton(onPressed: () => setState(() => _step++), child: const Text('İleri'))
                        : PrimaryButton(label: 'Kaydet', loading: _saving, onPressed: _save),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  List<Widget> _templateStep() => [
    GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      childAspectRatio: 0.82,
      children: [
        for (final t in widget.templates)
          _TemplateTile(
            template: t,
            names: _draft.texts.names,
            selected: t.id == _draft.templateId,
            locked: t.isPremium && !_hasPack,
            onTap: () =>
                t.isPremium && !_hasPack ? _openPaywall() : setState(() => _draft = _draft.copyWith(templateId: t.id)),
          ),
      ],
    ),
  ];

  List<Widget> _textsStep() {
    InputDecoration deco(String label, {String? hint}) => InputDecoration(labelText: label, hintText: hint);
    final deadlineLimit = AppFormat.toIstanbul(widget.event.startsAt);

    return [
      TextField(
        key: const Key('text-label'),
        controller: _label,
        maxLength: 60,
        decoration: deco('Etiket'),
        onChanged: (_) => _syncTexts(),
      ),
      const SizedBox(height: 8),
      TextField(
        key: const Key('text-intro'),
        controller: _intro,
        maxLength: 200,
        decoration: deco('Giriş', hint: 'Yılmaz ve Demir aileleri olarak'),
        onChanged: (_) => _syncTexts(),
      ),
      const SizedBox(height: 8),
      TextField(
        key: const Key('text-names'),
        controller: _names,
        maxLength: 120,
        decoration: deco('İsimler'),
        onChanged: (_) => _syncTexts(),
      ),
      const SizedBox(height: 8),
      TextField(
        key: const Key('text-message'),
        controller: _message,
        maxLength: 500,
        maxLines: 3,
        minLines: 2,
        decoration: deco('Mesaj'),
        onChanged: (_) => _syncTexts(),
      ),
      const SizedBox(height: 8),
      InkWell(
        borderRadius: BorderRadius.circular(AppRadius.input),
        onTap: () async {
          final today = DateTime.now();
          final last = DateTime(deadlineLimit.year, deadlineLimit.month, deadlineLimit.day);
          if (last.isBefore(DateTime(today.year, today.month, today.day))) return;
          final picked = await showDatePicker(
            context: context,
            initialDate: _draft.rsvpDeadline == null ? last : DateTime.parse(_draft.rsvpDeadline!),
            firstDate: DateTime(today.year, today.month, today.day),
            lastDate: last,
          );
          if (picked != null) {
            final ymd =
                '${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
            setState(() => _draft = _draft.copyWith(rsvpDeadline: ymd));
          }
        },
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: 'LCV son tarihi (isteğe bağlı)',
            helperText: 'Boş bırakırsan yanıtlar etkinlik başlayana kadar açık kalır.',
            suffixIcon: _draft.rsvpDeadline == null
                ? const Icon(Icons.event_outlined, size: 20)
                : IconButton(
                    tooltip: 'Son tarihi kaldır',
                    icon: const Icon(Icons.close, size: 20),
                    onPressed: () => setState(() => _draft = _draft.copyWith(clearDeadline: true)),
                  ),
          ),
          isEmpty: _draft.rsvpDeadline == null,
          child: Text(_draft.rsvpDeadline == null ? '' : AppFormat.calendarDate(_draft.rsvpDeadline!)),
        ),
      ),
    ];
  }

  List<Widget> _envelopeStep() => [
    Text('Zarf rengi', style: Theme.of(context).textTheme.titleMedium),
    const SizedBox(height: 10),
    Wrap(
      spacing: 14,
      runSpacing: 12,
      children: [
        for (final key in Palette.envelopeColors.keys)
          _ColorDot(
            key: Key('envelope-color-$key'),
            color: Palette.envelopeColors[key]!,
            label: Palette.envelopeNames[key]!,
            selected: _draft.envelopeColor == key,
            locked: !_hasPack && !FreeOptions.envelopeColors.contains(key),
            onTap: () => !_hasPack && !FreeOptions.envelopeColors.contains(key)
                ? _openPaywall()
                : setState(() => _draft = _draft.copyWith(envelopeColor: key)),
          ),
      ],
    ),
    const SizedBox(height: 22),
    Text('Mühür rengi', style: Theme.of(context).textTheme.titleMedium),
    const SizedBox(height: 10),
    Wrap(
      spacing: 14,
      runSpacing: 12,
      children: [
        for (final key in Palette.sealColors.keys)
          _ColorDot(
            key: Key('seal-color-$key'),
            color: Palette.sealColors[key]!,
            label: Palette.sealNames[key]!,
            selected: _draft.sealColor == key,
            locked: !_hasPack && !FreeOptions.sealColors.contains(key),
            onTap: () => !_hasPack && !FreeOptions.sealColors.contains(key)
                ? _openPaywall()
                : setState(() => _draft = _draft.copyWith(sealColor: key)),
          ),
      ],
    ),
    const SizedBox(height: 22),
    TextField(
      key: const Key('seal-text'),
      controller: _seal,
      maxLength: 4,
      textCapitalization: TextCapitalization.characters,
      inputFormatters: [LengthLimitingTextInputFormatter(4)],
      decoration: const InputDecoration(labelText: 'Mühür harfleri', hintText: 'E&E'),
      onChanged: (_) => _syncTexts(),
    ),
  ];
}

class _Preview extends StatelessWidget {
  const _Preview({required this.step, required this.draft, required this.template, required this.event});

  final int step;
  final InvitationDraft draft;
  final Template? template;
  final AppEvent event;

  @override
  Widget build(BuildContext context) {
    if (step == 2) {
      return Container(
        key: const Key('envelope-preview'),
        padding: const EdgeInsets.symmetric(vertical: 28),
        decoration: BoxDecoration(color: AppColors.stage, borderRadius: BorderRadius.circular(AppRadius.card)),
        child: Center(
          child: EnvelopeView(
            color: draft.envelopeColor,
            sealColor: draft.sealColor,
            sealText: draft.sealText,
            width: 240,
          ),
        ),
      );
    }
    if (template == null) return const SizedBox.shrink();
    return InvitationCard(
      key: const Key('card-preview'),
      config: template!.config,
      texts: draft.texts,
      startsAt: event.startsAt,
      venueName: event.venueName,
      venueAddress: event.venueAddress,
    );
  }
}

class _StepIndicator extends StatelessWidget {
  const _StepIndicator({required this.steps, required this.current, required this.onTap});

  final List<String> steps;
  final int current;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
      child: Row(
        children: [
          for (var i = 0; i < steps.length; i++)
            Expanded(
              child: InkWell(
                onTap: () => onTap(i),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 3, vertical: 6),
                  child: Column(
                    children: [
                      Container(
                        height: 4,
                        decoration: BoxDecoration(
                          color: i <= current ? AppColors.accent : AppColors.border,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        '${i + 1}. ${steps[i]}',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: i == current ? FontWeight.w700 : FontWeight.w500,
                          color: i == current ? AppColors.ink : AppColors.inkSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _TemplateTile extends StatelessWidget {
  const _TemplateTile({
    required this.template,
    required this.names,
    required this.selected,
    required this.locked,
    required this.onTap,
  });

  final Template template;
  final String names;
  final bool selected;
  final bool locked;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final c = template.config;
    return Semantics(
      selected: selected,
      button: true,
      label: '${template.name}${locked ? ', Etkinlik Paketi gerekli' : ''}',
      child: GestureDetector(
        key: Key('template-${template.slug}'),
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(
              child: Container(
                decoration: BoxDecoration(
                  color: c.paper,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: selected ? AppColors.accent : AppColors.border, width: selected ? 2 : 1),
                ),
                child: Stack(
                  children: [
                    Center(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(width: 28, height: 3, color: c.accent),
                            const SizedBox(height: 10),
                            Text(
                              names,
                              textAlign: TextAlign.center,
                              maxLines: 2,
                              style: c.displayFont == AppFonts.display
                                  ? AppFonts.displayStyle(size: 18, color: c.ink)
                                  : TextStyle(
                                      fontFamily: AppFonts.body,
                                      fontSize: 17,
                                      fontWeight: FontWeight.w600,
                                      color: c.ink,
                                    ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    if (locked) const Positioned(top: 8, right: 8, child: Pill(label: 'Paket', accent: true)),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 6),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                if (locked) const Icon(Icons.lock_outline, size: 14, color: AppColors.accent),
                if (locked) const SizedBox(width: 4),
                Text(template.name, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _ColorDot extends StatelessWidget {
  const _ColorDot({
    super.key,
    required this.color,
    required this.label,
    required this.selected,
    required this.locked,
    required this.onTap,
  });

  final Color color;
  final String label;
  final bool selected;
  final bool locked;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: selected,
      label: '$label${locked ? ', Etkinlik Paketi gerekli' : ''}',
      child: GestureDetector(
        onTap: onTap,
        child: SizedBox(
          width: 64,
          child: Column(
            children: [
              Container(
                width: 48,
                height: 48,
                padding: const EdgeInsets.all(3),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: selected ? AppColors.ink : Colors.transparent, width: 2),
                ),
                child: DecoratedBox(
                  decoration: BoxDecoration(color: color, shape: BoxShape.circle),
                  child: locked ? const Icon(Icons.lock_outline, size: 18, color: Colors.white) : null,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                label,
                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
