import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/format.dart';
import '../../../core/router/router.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../data/events_repository.dart';
import '../data/invitation.dart';

/// M15 — Gönder: özet (x uygulamaya, y linkle) → Gönder → link alıcıları listesi.
class SendScreen extends ConsumerStatefulWidget {
  const SendScreen({super.key, required this.eventId});

  final int eventId;

  @override
  ConsumerState<SendScreen> createState() => _SendScreenState();
}

class _SendScreenState extends ConsumerState<SendScreen> {
  SendResult? _result;
  bool _sending = false;
  String? _error;

  Future<void> _send() async {
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      final result = await ref.read(eventsRepositoryProvider).send(widget.eventId);
      refreshEvent(ref, widget.eventId);
      if (mounted) setState(() => _result = result);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_result == null ? 'Gönder' : 'Gönderildi')),
      body: _result != null ? SendResultView(result: _result!) : _summary(),
    );
  }

  Widget _summary() {
    final recipients = ref.watch(recipientsProvider(widget.eventId));
    return switch (recipients) {
      AsyncData(:final value) => _Summary(
        pending: value.where((r) => !r.isSent).toList(),
        sending: _sending,
        error: _error,
        onSend: _send,
        onAddRecipients: () => context.push(Routes.recipientsFor(widget.eventId)),
      ),
      AsyncError(:final error) => ErrorState(
        message: ApiException.from(error).message,
        onRetry: () => ref.invalidate(recipientsProvider(widget.eventId)),
      ),
      _ => const LoadingState(),
    };
  }
}

class _Summary extends StatelessWidget {
  const _Summary({
    required this.pending,
    required this.sending,
    required this.onSend,
    required this.onAddRecipients,
    this.error,
  });

  final List<Recipient> pending;
  final bool sending;
  final String? error;
  final VoidCallback onSend;
  final VoidCallback onAddRecipients;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    if (pending.isEmpty) {
      return Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const EmptyState(
            icon: Icons.mark_email_read_outlined,
            title: 'Gönderilecek yeni davetli yok',
            message: 'Eklediğin herkese davetiye gönderildi. Yeni davetli ekleyip tekrar gönderebilirsin.',
          ),
          TextButton(onPressed: onAddRecipients, child: const Text('Davetli ekle')),
        ],
      );
    }

    final inApp = pending.where((r) => r.isAppUser).length;
    final link = pending.length - inApp;

    return ListView(
      padding: const EdgeInsets.all(AppSizes.pagePadding),
      children: [
        Text('${pending.length} davetliye gönderilecek', style: theme.textTheme.headlineSmall),
        const SizedBox(height: 16),
        _SummaryRow(
          key: const Key('summary-in-app'),
          icon: Icons.mail_outline,
          count: inApp,
          title: 'uygulamaya',
          text: 'Posta kutularına zarf olarak düşer, bildirim alırlar.',
        ),
        const SizedBox(height: 10),
        _SummaryRow(
          key: const Key('summary-link'),
          icon: Icons.link,
          count: link,
          title: 'linkle',
          text: 'Her biri için kişiye özel bir link oluşur; WhatsApp\'tan sen gönderirsin.',
        ),
        ErrorText(error),
        const SizedBox(height: 24),
        PrimaryButton(label: 'Gönder', loading: sending, onPressed: onSend),
      ],
    );
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({super.key, required this.icon, required this.count, required this.title, required this.text});

  final IconData icon;
  final int count;
  final String title;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.card),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AppColors.accent),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('$count kişi $title', style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                const SizedBox(height: 2),
                Text(text, style: Theme.of(context).textTheme.bodyMedium),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Gönderim sonucu: link alıcıları, her birinde "WhatsApp'ta gönder" ve "Linki kopyala".
class SendResultView extends StatelessWidget {
  const SendResultView({super.key, required this.result});

  final SendResult result;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final links = result.linkRecipients;

    return ListView(
      padding: const EdgeInsets.all(AppSizes.pagePadding),
      children: [
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: AppColors.successSoft, borderRadius: BorderRadius.circular(AppRadius.input)),
          child: Text(
            result.inAppCount == 0 ? 'Davetiyeler hazır.' : '${result.inAppCount} kişinin posta kutusuna zarf düştü.',
            key: const Key('in-app-result'),
            style: const TextStyle(color: AppColors.success, fontWeight: FontWeight.w600),
          ),
        ),
        if (links.isNotEmpty) ...[
          const SizedBox(height: 20),
          Text('Linkle gönderilecekler (${links.length})', style: theme.textTheme.titleLarge),
          const SizedBox(height: 4),
          Text('Uygulaması olmayanlara davetiyeyi sen iletiyorsun.', style: theme.textTheme.bodyMedium),
          const SizedBox(height: 12),
          for (final r in links) LinkRecipientTile(recipient: r),
        ],
      ],
    );
  }
}

class LinkRecipientTile extends StatelessWidget {
  const LinkRecipientTile({super.key, required this.recipient});

  final LinkRecipient recipient;

  @override
  Widget build(BuildContext context) {
    final r = recipient;
    return Container(
      key: Key('link-recipient-${r.id}'),
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.card),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            r.name?.isNotEmpty ?? false ? r.name! : AppFormat.nationalPhone(r.phone),
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: AppColors.ink),
          ),
          if (r.name?.isNotEmpty ?? false)
            Text(AppFormat.nationalPhone(r.phone), style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: 10),
          WhatsAppButton(url: r.whatsappUrl, label: "WhatsApp'ta gönder"),
          const SizedBox(height: 8),
          CopyLinkButton(url: r.url),
        ],
      ),
    );
  }
}

class WhatsAppButton extends StatelessWidget {
  const WhatsAppButton({super.key, required this.url, required this.label});

  final String url;
  final String label;

  @override
  Widget build(BuildContext context) {
    return FilledButton.icon(
      onPressed: () async {
        final messenger = ScaffoldMessenger.of(context);
        final ok = await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication).catchError((_) => false);
        if (!ok) {
          messenger.showSnackBar(
            const SnackBar(content: Text('WhatsApp açılamadı. Linki kopyalayıp gönderebilirsin.')),
          );
        }
      },
      style: FilledButton.styleFrom(
        backgroundColor: AppColors.success,
        minimumSize: const Size.fromHeight(44),
        padding: const EdgeInsets.symmetric(horizontal: 8),
      ),
      icon: const Icon(Icons.chat_outlined, size: 18),
      label: Text(label, style: const TextStyle(fontSize: 14)),
    );
  }
}

class CopyLinkButton extends StatelessWidget {
  const CopyLinkButton({super.key, required this.url});

  final String url;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton.icon(
      onPressed: () async {
        await Clipboard.setData(ClipboardData(text: url));
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Link kopyalandı.')));
        }
      },
      style: OutlinedButton.styleFrom(
        foregroundColor: AppColors.ink,
        side: const BorderSide(color: AppColors.border),
        minimumSize: const Size.fromHeight(44),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.button)),
      ),
      icon: const Icon(Icons.copy, size: 18),
      label: const Text('Linki kopyala', style: TextStyle(fontSize: 14)),
    );
  }
}
