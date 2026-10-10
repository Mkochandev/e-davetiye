import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/format.dart';
import '../../../core/router/router.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../data/event.dart';
import '../data/events_repository.dart';

/// M10 — Etkinlik oluştur: tür, başlık, tarih-saat, mekan adı, adres.
class EventCreateScreen extends ConsumerStatefulWidget {
  const EventCreateScreen({super.key});

  @override
  ConsumerState<EventCreateScreen> createState() => _EventCreateScreenState();
}

class _EventCreateScreenState extends ConsumerState<EventCreateScreen> {
  final _title = TextEditingController();
  final _venue = TextEditingController();
  final _address = TextEditingController();
  EventType _type = EventType.wedding;
  DateTime? _date;
  TimeOfDay _time = const TimeOfDay(hour: 19, minute: 0);
  Map<String, String> _errors = {};
  String? _error;
  bool _saving = false;

  @override
  void dispose() {
    _title.dispose();
    _venue.dispose();
    _address.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _date ?? now.add(const Duration(days: 30)),
      firstDate: DateTime(now.year, now.month, now.day),
      lastDate: DateTime(now.year + 3),
    );
    if (picked != null) setState(() => _date = picked);
  }

  Future<void> _pickTime() async {
    final picked = await showTimePicker(context: context, initialTime: _time);
    if (picked != null) setState(() => _time = picked);
  }

  Future<void> _submit() async {
    final errors = <String, String>{
      if (_title.text.trim().length < 2) 'title': 'Etkinliğe bir başlık ver (ör. Elif & Emre).',
      if (_date == null) 'starts_at': 'Tarih seç.',
      if (_venue.text.trim().isEmpty) 'venue_name': 'Mekan adını yaz.',
    };
    setState(() {
      _errors = errors;
      _error = null;
    });
    if (errors.isNotEmpty) return;

    final d = _date!;
    setState(() => _saving = true);
    try {
      final event = await ref
          .read(eventsRepositoryProvider)
          .create(
            EventDraft(
              type: _type,
              title: _title.text.trim(),
              startsAtLocal: DateTime(d.year, d.month, d.day, _time.hour, _time.minute),
              venueName: _venue.text.trim(),
              venueAddress: _address.text.trim(),
            ),
          );
      ref.invalidate(eventsProvider);
      if (mounted) context.pushReplacement(Routes.eventFor(event.id));
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _errors = e.fieldErrors;
          _error = e.fieldErrors.isEmpty ? e.message : null;
        });
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Yeni etkinlik')),
      body: ListView(
        padding: const EdgeInsets.all(AppSizes.pagePadding),
        children: [
          Text('Tür', style: theme.textTheme.titleMedium),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final t in EventType.values)
                ChoiceChip(
                  label: Text(t.label),
                  selected: _type == t,
                  onSelected: (_) => setState(() => _type = t),
                  selectedColor: AppColors.accentSoft,
                  labelStyle: TextStyle(
                    color: _type == t ? AppColors.accent : AppColors.ink,
                    fontWeight: FontWeight.w600,
                  ),
                  side: BorderSide(color: _type == t ? AppColors.accent : AppColors.border),
                  showCheckmark: false,
                ),
            ],
          ),
          const SizedBox(height: 20),
          TextField(
            key: const Key('event-title'),
            controller: _title,
            textCapitalization: TextCapitalization.words,
            decoration: InputDecoration(labelText: 'Başlık', hintText: 'Elif & Emre', errorText: _errors['title']),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _PickerField(
                  key: const Key('event-date'),
                  label: 'Tarih',
                  value: _date == null ? null : DateFormat('d MMMM y, EEEE', AppFormat.locale).format(_date!),
                  icon: Icons.calendar_today_outlined,
                  error: _errors['starts_at'],
                  onTap: _pickDate,
                ),
              ),
              const SizedBox(width: 12),
              SizedBox(
                width: 120,
                child: _PickerField(
                  label: 'Saat',
                  value: _time.format(context),
                  icon: Icons.schedule,
                  onTap: _pickTime,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          TextField(
            key: const Key('event-venue'),
            controller: _venue,
            textCapitalization: TextCapitalization.words,
            decoration: InputDecoration(labelText: 'Mekan adı', errorText: _errors['venue_name']),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _address,
            decoration: InputDecoration(labelText: 'Adres (isteğe bağlı)', errorText: _errors['venue_address']),
          ),
          ErrorText(_error),
          const SizedBox(height: 24),
          PrimaryButton(label: 'Etkinliği oluştur', loading: _saving, onPressed: _submit),
        ],
      ),
    );
  }
}

class _PickerField extends StatelessWidget {
  const _PickerField({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    required this.onTap,
    this.error,
  });

  final String label;
  final String? value;
  final IconData icon;
  final VoidCallback onTap;
  final String? error;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.input),
      child: InputDecorator(
        decoration: InputDecoration(labelText: label, errorText: error, suffixIcon: Icon(icon, size: 20)),
        isEmpty: value == null,
        child: Text(value ?? '', maxLines: 1, overflow: TextOverflow.ellipsis),
      ),
    );
  }
}
