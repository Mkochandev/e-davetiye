import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/router/router.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../data/auth_repository.dart';
import '../data/phone_input.dart';

/// M2 — Telefon girişi.
class PhoneScreen extends ConsumerStatefulWidget {
  const PhoneScreen({super.key});

  @override
  ConsumerState<PhoneScreen> createState() => _PhoneScreenState();
}

class _PhoneScreenState extends ConsumerState<PhoneScreen> {
  final _controller = TextEditingController();
  String? _error;
  bool _loading = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final error = PhoneInput.validate(_controller.text);
    setState(() => _error = error);
    if (error != null) return;

    final phone = PhoneInput.toE164(_controller.text);
    setState(() => _loading = true);
    try {
      await ref.read(authRepositoryProvider).requestOtp(phone);
      if (mounted) context.push(Routes.codeFor(phone));
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSizes.pagePadding),
          children: [
            Text('Telefon numaran', style: theme.textTheme.headlineMedium),
            const SizedBox(height: 8),
            Text('Numarana 6 haneli bir giriş kodu göndereceğiz.', style: theme.textTheme.bodyMedium),
            const SizedBox(height: 28),
            TextField(
              key: const Key('phone-field'),
              controller: _controller,
              autofocus: true,
              keyboardType: TextInputType.phone,
              autofillHints: const [AutofillHints.telephoneNumberNational],
              inputFormatters: [PhoneInputFormatter()],
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w600, letterSpacing: 0.5),
              decoration: const InputDecoration(
                hintText: '5XX XXX XX XX',
                prefixIcon: Padding(
                  padding: EdgeInsets.only(left: 16, right: 8),
                  child: Text('+90', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w600, color: AppColors.ink)),
                ),
                prefixIconConstraints: BoxConstraints(minWidth: 0, minHeight: 0),
              ),
              onChanged: (_) {
                if (_error != null) setState(() => _error = null);
              },
              onSubmitted: (_) => _submit(),
            ),
            ErrorText(_error),
            const SizedBox(height: 24),
            PrimaryButton(label: 'Kod gönder', loading: _loading, onPressed: _submit),
          ],
        ),
      ),
    );
  }
}
