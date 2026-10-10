import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/router.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/envelope_art.dart';

/// M1 — Tanıtım.
class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.stage,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSizes.pagePadding),
          child: Column(
            children: [
              const Spacer(flex: 2),
              const EnvelopeArt(width: 260),
              const SizedBox(height: 48),
              Text(
                'Davetiyen zarfında gelsin',
                textAlign: TextAlign.center,
                style: AppFonts.displayStyle(size: 30, weight: FontWeight.w600, color: Colors.white, height: 1.2),
              ),
              const SizedBox(height: 12),
              const Text(
                'Davetiyeni hazırla, sevdiklerine gönder; gelen zarfları tek yerde aç.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white70, fontSize: 16, height: 1.5),
              ),
              const Spacer(flex: 3),
              FilledButton(onPressed: () => context.push(Routes.phone), child: const Text('Başla')),
            ],
          ),
        ),
      ),
    );
  }
}
