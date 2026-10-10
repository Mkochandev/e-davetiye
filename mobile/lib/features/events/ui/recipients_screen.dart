import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/format.dart';
import '../../../core/router/router.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../auth/data/phone_input.dart';
import '../data/event.dart';
import '../data/events_repository.dart';
import '../data/invitation.dart';
import '../data/recipient_limit.dart';

/// M13 — Davetliler: liste, `x/30` sayacı, rehberden seçme, elle ekleme, açılmamış davetliyi silme.
class RecipientsScreen extends ConsumerWidget {
  const RecipientsScreen({super.key, required this.eventId});

  final int eventId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final event = ref.watch(eventProvider(eventId));
    final recipients = ref.watch(recipientsProvider(eventId));

    final error = event.error ?? recipients.error;
    return Scaffold(
      appBar: AppBar(title: const Text('Davetliler')),
      body: switch ((event.value, recipients.value)) {
        _ when error != null => ErrorState(
          message: ApiException.from(error).message,
          onRetry: () => refreshEvent(ref, eventId),
        ),
        (final AppEvent e, final List<Recipient> list) => _Body(event: e, recipients: list),
        _ => const LoadingState(),
      },
    );
  }
}

class _Body extends ConsumerWidget {
  const _Body({required this.event, required this.recipients});

  final AppEvent event;
  final List<Recipient> recipients;

  Future<void> _addManually(BuildContext context, WidgetRef ref) async {
    final person = await showModalBottomSheet<({String phone, String? name})>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => const _ManualAddSheet(),
    );
    if (person == null || !context.mounted) return;

    final limit = RecipientLimit(
      existing: recipients.length,
      selected: 1,
      limit: event.recipientLimit,
      hasPack: event.hasPack,
    );
    if (limit.needsPack) {
      context.push(Routes.paywallFor(event.id));
      return;
    }

    final messenger = ScaffoldMessenger.of(context);
    try {
      final result = await ref.read(eventsRepositoryProvider).addRecipients(event.id, [person]);
      refreshEvent(ref, event.id);
      messenger.showSnackBar(
        SnackBar(content: Text(result.added == 0 ? 'Bu numara zaten listede.' : 'Davetli eklendi.')),
      );
    } on ApiException catch (e) {
      if (e.isPackRequired && context.mounted) {
        context.push(Routes.paywallFor(event.id));
      } else {
        messenger.showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  Future<void> _delete(BuildContext context, WidgetRef ref, Recipient r) async {
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref.read(eventsRepositoryProvider).deleteRecipient(event.id, r.id);
      refreshEvent(ref, event.id);
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final limit = RecipientLimit(
      existing: recipients.length,
      selected: 0,
      limit: event.recipientLimit,
      hasPack: event.hasPack,
    );
    final theme = Theme.of(context);

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
          child: Row(
            children: [
              Text(limit.label, key: const Key('recipient-counter'), style: theme.textTheme.headlineSmall),
              const SizedBox(width: 8),
              Expanded(
                child: event.hasPack
                    ? const SizedBox.shrink()
                    : Text(
                        'ücretsiz sınır',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium,
                      ),
              ),
              if (!event.hasPack)
                TextButton(
                  onPressed: () => context.push(Routes.paywallFor(event.id)),
                  child: const Text('Sınırı kaldır'),
                ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(
            children: [
              Expanded(
                child: FilledButton.icon(
                  key: const Key('pick-contacts'),
                  onPressed: () => context.push(Routes.contactsFor(event.id)),
                  icon: const Icon(Icons.contacts_outlined),
                  label: const Text('Rehberden seç'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton.icon(
                  key: const Key('add-manually'),
                  onPressed: () => _addManually(context, ref),
                  icon: const Icon(Icons.dialpad),
                  label: const Text('Numara ekle'),
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size.fromHeight(52),
                    foregroundColor: AppColors.ink,
                    side: const BorderSide(color: AppColors.border),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.button)),
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        Expanded(
          child: recipients.isEmpty
              ? const EmptyState(
                  icon: Icons.group_outlined,
                  title: 'Henüz davetli yok',
                  message: 'Rehberinden seç ya da numarayı elle ekle.',
                )
              : ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                  itemCount: recipients.length,
                  separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
                  itemBuilder: (_, i) {
                    final r = recipients[i];
                    return ListTile(
                      key: Key('recipient-${r.id}'),
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        r.name?.isNotEmpty ?? false ? r.name! : AppFormat.nationalPhone(r.phone),
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                      subtitle: Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Wrap(
                          spacing: 6,
                          runSpacing: 4,
                          children: [
                            if (r.name?.isNotEmpty ?? false) Text(AppFormat.nationalPhone(r.phone)),
                            if (r.isAppUser) const Pill(label: 'Uygulamada', success: true),
                            if (r.isOpened)
                              const Pill(label: 'Açtı')
                            else if (r.isSent)
                              const Pill(label: 'Gönderildi'),
                          ],
                        ),
                      ),
                      trailing: r.isOpened
                          ? null
                          : IconButton(
                              tooltip: 'Davetliyi çıkar',
                              icon: const Icon(Icons.close, color: AppColors.inkSecondary),
                              onPressed: () => _delete(context, ref, r),
                            ),
                    );
                  },
                ),
        ),
      ],
    );
  }
}

class _ManualAddSheet extends StatefulWidget {
  const _ManualAddSheet();

  @override
  State<_ManualAddSheet> createState() => _ManualAddSheetState();
}

class _ManualAddSheetState extends State<_ManualAddSheet> {
  final _name = TextEditingController();
  final _phone = TextEditingController();
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  void _submit() {
    final digits = PhoneInput.digits(_phone.text);
    if (digits.length != PhoneInput.length) {
      setState(() => _error = PhoneInput.invalidMessage);
      return;
    }
    Navigator.of(context).pop((phone: PhoneInput.toE164(_phone.text), name: _name.text.trim()));
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Numara ekle', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 16),
          TextField(
            key: const Key('manual-name'),
            controller: _name,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(labelText: 'Ad (isteğe bağlı)'),
          ),
          const SizedBox(height: 12),
          TextField(
            key: const Key('manual-phone'),
            controller: _phone,
            keyboardType: TextInputType.phone,
            inputFormatters: [PhoneInputFormatter()],
            decoration: InputDecoration(
              labelText: 'Telefon',
              prefixText: '+90 ',
              hintText: '5XX XXX XX XX',
              errorText: _error,
            ),
            onSubmitted: (_) => _submit(),
          ),
          const SizedBox(height: 20),
          FilledButton(onPressed: _submit, child: const Text('Ekle')),
        ],
      ),
    );
  }
}
