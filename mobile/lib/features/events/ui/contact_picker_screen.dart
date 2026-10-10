import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/format.dart';
import '../../../core/router/router.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../data/contacts_source.dart';
import '../data/event.dart';
import '../data/events_repository.dart';
import '../data/recipient_limit.dart';

/// M13 rehberden seçim: önce izin açıklaması, sonra çoklu seçim + "Uygulamada" rozeti + sayaç.
class ContactPickerScreen extends ConsumerStatefulWidget {
  const ContactPickerScreen({super.key, required this.eventId});

  final int eventId;

  @override
  ConsumerState<ContactPickerScreen> createState() => _ContactPickerScreenState();
}

enum _Stage { explain, loading, denied, list }

class _ContactPickerScreenState extends ConsumerState<ContactPickerScreen> {
  _Stage _stage = _Stage.explain;
  bool _permanentlyDenied = false;
  List<PhoneContact> _contacts = [];
  Set<String> _registered = {};
  final Set<String> _selected = {};
  String _query = '';
  String? _error;
  bool _saving = false;

  Future<void> _allow() async {
    final source = ref.read(contactsSourceProvider);
    final access = await source.requestAccess();
    if (access != ContactsAccess.granted) {
      setState(() {
        _stage = _Stage.denied;
        _permanentlyDenied = access == ContactsAccess.permanentlyDenied;
      });
      return;
    }

    setState(() => _stage = _Stage.loading);
    try {
      final contacts = await source.load();
      // Sunucuya yalnız hash'ler gider; kim olduğu gönderilmez ve saklanmaz (spec §14).
      Set<String> registered = {};
      try {
        registered = await ref.read(eventsRepositoryProvider).matchContacts(contacts.map((c) => c.hash).toList());
      } on ApiException {
        // Rozet gösterilemese de seçim yapılabilir.
      }
      if (!mounted) return;
      setState(() {
        _contacts = contacts;
        _registered = registered;
        _stage = _Stage.list;
      });
    } catch (_) {
      if (mounted) {
        setState(() {
          _stage = _Stage.denied;
          _error = 'Rehber okunamadı.';
        });
      }
    }
  }

  Future<void> _add(AppEvent event, int existing) async {
    final limit = RecipientLimit(
      existing: existing,
      selected: _selected.length,
      limit: event.recipientLimit,
      hasPack: event.hasPack,
    );
    if (limit.needsPack) {
      context.push(Routes.paywallFor(event.id));
      return;
    }

    final people = _contacts
        .where((c) => _selected.contains(c.phone))
        .map((c) => (phone: c.phone, name: c.name))
        .toList();
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final result = await ref.read(eventsRepositoryProvider).addRecipients(event.id, people);
      refreshEvent(ref, event.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            result.skipped == 0
                ? '${result.added} davetli eklendi.'
                : '${result.added} davetli eklendi, ${result.skipped} kişi zaten listedeydi.',
          ),
        ),
      );
      context.pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.isPackRequired) {
        context.push(Routes.paywallFor(event.id));
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
      appBar: AppBar(title: const Text('Rehberden seç')),
      body: switch (_stage) {
        _Stage.explain => _Explain(onAllow: _allow),
        _Stage.loading => const LoadingState(),
        _Stage.denied => _Denied(permanently: _permanentlyDenied, message: _error, onRetry: _allow),
        _Stage.list => _buildList(),
      },
    );
  }

  Widget _buildList() {
    final event = ref.watch(eventProvider(widget.eventId)).value;
    final existingList = ref.watch(recipientsProvider(widget.eventId)).value;
    if (event == null || existingList == null) return const LoadingState();

    final already = existingList.map((r) => r.phone).toSet();
    final q = _query.toLowerCase();
    final visible = _contacts
        .where((c) => q.isEmpty || c.name.toLowerCase().contains(q) || c.phone.contains(q))
        .toList();
    final limit = RecipientLimit(
      existing: existingList.length,
      selected: _selected.length,
      limit: event.recipientLimit,
      hasPack: event.hasPack,
    );

    if (_contacts.isEmpty) {
      return const EmptyState(
        icon: Icons.contacts_outlined,
        title: 'Rehberde numara yok',
        message: 'Davetlileri elle de ekleyebilirsin.',
      );
    }

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
          child: TextField(
            decoration: const InputDecoration(hintText: 'Ara', prefixIcon: Icon(Icons.search)),
            onChanged: (v) => setState(() => _query = v.trim()),
          ),
        ),
        Expanded(
          child: ListView.builder(
            itemCount: visible.length,
            itemBuilder: (_, i) {
              final c = visible[i];
              final isAlready = already.contains(c.phone);
              return CheckboxListTile(
                key: Key('contact-${c.phone}'),
                value: isAlready || _selected.contains(c.phone),
                onChanged: isAlready
                    ? null
                    : (v) => setState(() => v == true ? _selected.add(c.phone) : _selected.remove(c.phone)),
                controlAffinity: ListTileControlAffinity.leading,
                title: Text(
                  c.name.isEmpty ? AppFormat.nationalPhone(c.phone) : c.name,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                subtitle: Text(
                  isAlready ? '${AppFormat.nationalPhone(c.phone)} · listede' : AppFormat.nationalPhone(c.phone),
                ),
                secondary: _registered.contains(c.hash) ? const Pill(label: 'Uygulamada', success: true) : null,
              );
            },
          ),
        ),
        SafeArea(
          top: false,
          child: Container(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
            decoration: const BoxDecoration(
              color: AppColors.surface,
              border: Border(top: BorderSide(color: AppColors.border)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    Text(
                      limit.label,
                      key: const Key('picker-counter'),
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                        color: limit.exceeded ? AppColors.accent : AppColors.ink,
                      ),
                    ),
                    const SizedBox(width: 8),
                    if (limit.needsPack)
                      const Expanded(
                        child: Text(
                          'Ücretsiz sınır aşıldı',
                          style: TextStyle(color: AppColors.accent, fontWeight: FontWeight.w600),
                        ),
                      ),
                  ],
                ),
                ErrorText(_error),
                const SizedBox(height: 10),
                PrimaryButton(
                  label: limit.needsPack
                      ? 'Sınırı kaldır'
                      : (_selected.isEmpty ? 'Kişi seç' : '${_selected.length} kişiyi ekle'),
                  loading: _saving,
                  onPressed: _selected.isEmpty ? null : () => _add(event, existingList.length),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _Explain extends StatelessWidget {
  const _Explain({required this.onAllow});

  final VoidCallback onAllow;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return ListView(
      padding: const EdgeInsets.all(AppSizes.pagePadding),
      children: [
        const SizedBox(height: 16),
        Center(
          child: Container(
            width: 72,
            height: 72,
            decoration: const BoxDecoration(color: AppColors.accentSoft, shape: BoxShape.circle),
            child: const Icon(Icons.contacts_outlined, color: AppColors.accent, size: 32),
          ),
        ),
        const SizedBox(height: 20),
        Text('Davetlilerini rehberinden seç', style: theme.textTheme.headlineSmall, textAlign: TextAlign.center),
        const SizedBox(height: 16),
        for (final line in const [
          'Rehberin telefonunda okunur; sunucumuza toplu olarak yüklenmez.',
          'Kimlerin uygulamayı kullandığını göstermek için numaraların yalnız şifrelenmiş özetleri gönderilir ve saklanmaz.',
          'Sadece senin seçtiğin kişilerin numarası davetli listene eklenir.',
        ])
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Padding(
                  padding: EdgeInsets.only(top: 2),
                  child: Icon(Icons.check_circle_outline, size: 20, color: AppColors.success),
                ),
                const SizedBox(width: 10),
                Expanded(child: Text(line, style: theme.textTheme.bodyLarge)),
              ],
            ),
          ),
        const SizedBox(height: 16),
        FilledButton(
          key: const Key('allow-contacts'),
          onPressed: onAllow,
          child: const Text('Rehbere erişime izin ver'),
        ),
      ],
    );
  }
}

class _Denied extends StatelessWidget {
  const _Denied({required this.permanently, required this.onRetry, this.message});

  final bool permanently;
  final String? message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              message ??
                  (permanently
                      ? 'Rehber izni kapalı. Telefonunun ayarlarından Zarf için rehber iznini açabilir ya da numaraları elle ekleyebilirsin.'
                      : 'Rehber izni verilmedi. Numaraları elle de ekleyebilirsin.'),
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyLarge,
            ),
            const SizedBox(height: 16),
            if (!permanently) OutlinedButton(onPressed: onRetry, child: const Text('Tekrar dene')),
          ],
        ),
      ),
    );
  }
}
