import 'package:flutter/material.dart';

import '../theme/tokens.dart';

/// İşlem sürerken dönen göstergeli ana buton.
class PrimaryButton extends StatelessWidget {
  const PrimaryButton({super.key, required this.label, required this.onPressed, this.loading = false});

  final String label;
  final VoidCallback? onPressed;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return FilledButton(
      onPressed: loading ? null : onPressed,
      child: loading
          ? const SizedBox.square(dimension: 22, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white))
          : Text(label),
    );
  }
}

/// Form altındaki hata satırı (API ya da doğrulama).
class ErrorText extends StatelessWidget {
  const ErrorText(this.message, {super.key});

  final String? message;

  @override
  Widget build(BuildContext context) {
    if (message == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Text(
        message!,
        key: const Key('error-text'),
        style: const TextStyle(color: AppColors.accent, fontSize: 14, fontWeight: FontWeight.w500),
      ),
    );
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.icon, required this.title, required this.message});

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 72,
              height: 72,
              decoration: const BoxDecoration(color: AppColors.accentSoft, shape: BoxShape.circle),
              child: Icon(icon, color: AppColors.accent, size: 32),
            ),
            const SizedBox(height: 20),
            Text(title, style: theme.textTheme.titleLarge, textAlign: TextAlign.center),
            const SizedBox(height: 8),
            Text(message, style: theme.textTheme.bodyMedium, textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}
