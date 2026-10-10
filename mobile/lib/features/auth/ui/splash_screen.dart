import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/theme/tokens.dart';
import '../data/session.dart';

/// Açılışta oturum kontrol edilirken; sunucuya ulaşılamazsa tekrar deneme.
class SplashScreen extends ConsumerWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);

    return Scaffold(
      backgroundColor: AppColors.stage,
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('Zarf', style: AppFonts.displayStyle(size: 40, weight: FontWeight.w600, color: Colors.white)),
                const SizedBox(height: 32),
                if (session case SessionUnreachable(:final message)) ...[
                  Text(message, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white70)),
                  const SizedBox(height: 20),
                  FilledButton(
                    onPressed: () => ref.read(sessionProvider.notifier).restore(),
                    child: const Text('Tekrar dene'),
                  ),
                ] else
                  const SizedBox.square(
                    dimension: 28,
                    child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white70),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
