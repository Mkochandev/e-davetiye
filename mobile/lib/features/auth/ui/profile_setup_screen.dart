import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/config.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../data/auth_repository.dart';
import '../data/session.dart';

/// M4 — Profil tamamlama: ad soyad + KVKK açık rıza. Ad ya da rıza eksikse gösterilir.
class ProfileSetupScreen extends ConsumerStatefulWidget {
  const ProfileSetupScreen({super.key});

  @override
  ConsumerState<ProfileSetupScreen> createState() => _ProfileSetupScreenState();
}

class _ProfileSetupScreenState extends ConsumerState<ProfileSetupScreen> {
  late final TextEditingController _name;
  late final TapGestureRecognizer _noticeTap;
  bool _consent = false;
  bool _loading = false;
  String? _nameError;
  String? _consentError;
  String? _error;

  @override
  void initState() {
    super.initState();
    final session = ref.read(sessionProvider);
    _name = TextEditingController(text: session is SignedIn ? session.user.name : null);
    _noticeTap = TapGestureRecognizer()..onTap = _openNotice;
  }

  @override
  void dispose() {
    _name.dispose();
    _noticeTap.dispose();
    super.dispose();
  }

  Future<void> _openNotice() async {
    await launchUrl(AppConfig.webPage('/aydinlatma-metni'), mode: LaunchMode.inAppBrowserView);
  }

  Future<void> _submit() async {
    final name = _name.text.trim();
    setState(() {
      _nameError = name.length < 2 ? 'Adını ve soyadını yaz.' : null;
      _consentError = _consent ? null : 'Devam etmek için açık rızanı onaylamalısın.';
      _error = null;
    });
    if (_nameError != null || _consentError != null) return;

    setState(() => _loading = true);
    try {
      final user = await ref.read(authRepositoryProvider).completeProfile(name);
      ref.read(sessionProvider.notifier).updateUser(user);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSizes.pagePadding),
          children: [
            const SizedBox(height: 24),
            Text('Seni tanıyalım', style: theme.textTheme.headlineMedium),
            const SizedBox(height: 8),
            Text('Adın, gönderdiğin davetiyelerde ve verdiğin yanıtlarda görünür.', style: theme.textTheme.bodyMedium),
            const SizedBox(height: 28),
            TextField(
              key: const Key('name-field'),
              controller: _name,
              textCapitalization: TextCapitalization.words,
              autofillHints: const [AutofillHints.name],
              decoration: InputDecoration(hintText: 'Ad soyad', errorText: _nameError),
            ),
            const SizedBox(height: 20),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox.square(
                  dimension: AppSizes.minTap,
                  child: Checkbox(
                    key: const Key('consent-checkbox'),
                    value: _consent,
                    onChanged: (v) => setState(() {
                      _consent = v ?? false;
                      if (_consent) _consentError = null;
                    }),
                  ),
                ),
                const SizedBox(width: 4),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(top: 11),
                    child: Text.rich(
                      TextSpan(
                        style: theme.textTheme.bodyMedium?.copyWith(color: AppColors.ink),
                        children: [
                          TextSpan(
                            text: 'Aydınlatma metnini',
                            style: const TextStyle(
                              color: AppColors.accent,
                              fontWeight: FontWeight.w600,
                              decoration: TextDecoration.underline,
                            ),
                            recognizer: _noticeTap,
                          ),
                          const TextSpan(text: ' okudum; kişisel verilerimin işlenmesine açık rıza veriyorum.'),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
            ErrorText(_consentError ?? _error),
            const SizedBox(height: 28),
            PrimaryButton(label: 'Devam et', loading: _loading, onPressed: _submit),
          ],
        ),
      ),
    );
  }
}
