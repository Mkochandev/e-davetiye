import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/format.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../data/auth_repository.dart';
import '../data/session.dart';

/// M3 — Kod doğrulama. Başarılı girişte yönlendirmeyi router yapar (M4 ya da sekmeler).
class CodeScreen extends ConsumerStatefulWidget {
  const CodeScreen({super.key, required this.phone});

  /// E.164.
  final String phone;

  static const codeLength = 6;
  static const resendAfter = Duration(seconds: 60);

  @override
  ConsumerState<CodeScreen> createState() => _CodeScreenState();
}

class _CodeScreenState extends ConsumerState<CodeScreen> {
  final _controller = TextEditingController();
  final _focus = FocusNode();
  Timer? _timer;
  late int _secondsLeft;
  String? _error;
  String? _info;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _startCountdown();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    _focus.dispose();
    super.dispose();
  }

  void _startCountdown() {
    _timer?.cancel();
    _secondsLeft = CodeScreen.resendAfter.inSeconds;
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      setState(() => _secondsLeft--);
      if (_secondsLeft <= 0) timer.cancel();
    });
  }

  Future<void> _verify() async {
    final code = _controller.text;
    if (code.length != CodeScreen.codeLength || _loading) return;

    setState(() {
      _loading = true;
      _error = null;
      _info = null;
    });
    try {
      final result = await ref.read(authRepositoryProvider).verify(widget.phone, code);
      await ref.read(sessionProvider.notifier).signIn(result.token, result.user);
    } on ApiException catch (e) {
      _controller.clear();
      _focus.requestFocus();
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _resend() async {
    setState(() {
      _error = null;
      _info = null;
    });
    try {
      await ref.read(authRepositoryProvider).requestOtp(widget.phone);
      _controller.clear();
      setState(() {
        _info = 'Yeni kod gönderildi.';
        _startCountdown();
      });
    } on ApiException catch (e) {
      setState(() => _error = e.message);
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
            Text('Kodu gir', style: theme.textTheme.headlineMedium),
            const SizedBox(height: 8),
            Text(
              '${AppFormat.nationalPhone(widget.phone)} numarasına gönderdiğimiz 6 haneli kodu yaz.',
              style: theme.textTheme.bodyMedium,
            ),
            const SizedBox(height: 28),
            _CodeBoxes(controller: _controller, focusNode: _focus, enabled: !_loading, onCompleted: _verify),
            ErrorText(_error),
            if (_info != null)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Text(
                  _info!,
                  style: const TextStyle(color: AppColors.success, fontWeight: FontWeight.w500),
                ),
              ),
            const SizedBox(height: 24),
            PrimaryButton(label: 'Doğrula', loading: _loading, onPressed: _verify),
            const SizedBox(height: 8),
            Center(
              child: _secondsLeft > 0
                  ? Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Text('Yeni kod $_secondsLeft sn sonra istenebilir', style: theme.textTheme.bodyMedium),
                    )
                  : TextButton(onPressed: _resend, child: const Text('Tekrar gönder')),
            ),
          ],
        ),
      ),
    );
  }
}

/// Görünmez tek bir alan + 6 kutucuk. SMS kodu otomatik doldurmayı ve yapıştırmayı destekler.
class _CodeBoxes extends StatelessWidget {
  const _CodeBoxes({
    required this.controller,
    required this.focusNode,
    required this.enabled,
    required this.onCompleted,
  });

  final TextEditingController controller;
  final FocusNode focusNode;
  final bool enabled;
  final VoidCallback onCompleted;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Opacity(
          opacity: 0,
          child: TextField(
            key: const Key('code-field'),
            controller: controller,
            focusNode: focusNode,
            enabled: enabled,
            autofocus: true,
            keyboardType: TextInputType.number,
            autofillHints: const [AutofillHints.oneTimeCode],
            inputFormatters: [
              FilteringTextInputFormatter.digitsOnly,
              LengthLimitingTextInputFormatter(CodeScreen.codeLength),
            ],
            onChanged: (value) {
              if (value.length == CodeScreen.codeLength) onCompleted();
            },
          ),
        ),
        Positioned.fill(
          child: GestureDetector(
            behavior: HitTestBehavior.opaque,
            onTap: focusNode.requestFocus,
            child: ListenableBuilder(
              listenable: Listenable.merge([controller, focusNode]),
              builder: (context, _) {
                final code = controller.text;
                return Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: List.generate(CodeScreen.codeLength, (i) {
                    final active = focusNode.hasFocus && i == code.length.clamp(0, CodeScreen.codeLength - 1);
                    return Container(
                      width: 48,
                      height: 58,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: AppColors.surface,
                        borderRadius: BorderRadius.circular(AppRadius.input),
                        border: Border.all(color: active ? AppColors.ink : AppColors.border, width: active ? 1.5 : 1),
                      ),
                      child: Text(
                        i < code.length ? code[i] : '',
                        style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w600, color: AppColors.ink),
                      ),
                    );
                  }),
                );
              },
            ),
          ),
        ),
      ],
    );
  }
}
