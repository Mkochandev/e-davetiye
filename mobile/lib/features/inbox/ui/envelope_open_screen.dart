import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/invitation/envelope_view.dart';
import '../../../core/router/router.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/tokens.dart';
import '../data/envelope.dart';
import '../data/inbox_repository.dart';

/// M7 — Zarf açma: koyu tam ekran, mühre dokununca titreşim + animasyon + `open`, sonra "Davetiyeyi oku".
class EnvelopeOpenScreen extends ConsumerStatefulWidget {
  const EnvelopeOpenScreen({super.key, required this.id});

  final int id;

  static const duration = Duration(milliseconds: 1600);

  @override
  ConsumerState<EnvelopeOpenScreen> createState() => _EnvelopeOpenScreenState();
}

class _EnvelopeOpenScreenState extends ConsumerState<EnvelopeOpenScreen> with SingleTickerProviderStateMixin {
  late final AnimationController _animation = AnimationController(vsync: this, duration: EnvelopeOpenScreen.duration);

  @override
  void dispose() {
    _animation.dispose();
    super.dispose();
  }

  void _open() {
    if (_animation.isAnimating || _animation.isCompleted) return;
    HapticFeedback.mediumImpact();
    _animation.forward();
    ref.read(envelopeProvider(widget.id).notifier).markOpened();
  }

  @override
  Widget build(BuildContext context) {
    final detail = ref.watch(envelopeProvider(widget.id));

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: AppColors.stage,
        appBar: AppBar(
          backgroundColor: Colors.transparent,
          foregroundColor: Colors.white,
          leading: const CloseButton(),
        ),
        body: SafeArea(
          child: switch (detail) {
            AsyncData(:final value) => _buildStage(value),
            AsyncError(:final error) => _StageMessage(
              message: ApiException.from(error).message,
              action: TextButton(
                onPressed: () => ref.invalidate(envelopeProvider(widget.id)),
                child: const Text('Tekrar dene', style: TextStyle(color: Colors.white)),
              ),
            ),
            _ => const Center(child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white70)),
          },
        ),
      ),
    );
  }

  Widget _buildStage(EnvelopeDetail d) {
    final width = (MediaQuery.sizeOf(context).width - 48).clamp(0.0, 340.0);

    return AnimatedBuilder(
      animation: _animation,
      builder: (context, _) {
        final done = _animation.isCompleted;
        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            children: [
              const Spacer(),
              Text(
                d.title,
                textAlign: TextAlign.center,
                style: AppFonts.displayStyle(size: 26, weight: FontWeight.w600, color: Colors.white),
              ),
              const SizedBox(height: 6),
              Text(
                d.cancelled ? 'Bu etkinlik iptal edildi' : 'seni davet ediyor',
                style: TextStyle(color: d.cancelled ? const Color(0xFFF6E7EC) : Colors.white70, fontSize: 16),
              ),
              // Açılan kapak ve yukarı kayan kart başlığın üstüne binmesin.
              SizedBox(height: width * 0.42 + 16),
              EnvelopeView(
                color: d.envelopeColor,
                sealColor: d.sealColor,
                sealText: d.sealText,
                width: width,
                progress: _animation.value,
                onSealTap: _open,
                letter: Text(
                  d.texts.names.isEmpty ? d.title : d.texts.names,
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  style: AppFonts.displayStyle(size: 20),
                ),
              ),
              const Spacer(),
              SizedBox(
                height: 52,
                child: done
                    ? FilledButton(
                        onPressed: () => context.pushReplacement(Routes.invitationFor(widget.id)),
                        child: const Text('Davetiyeyi oku'),
                      )
                    : Center(
                        child: Text(
                          _animation.isAnimating ? '' : 'Mührü açmak için dokun',
                          style: const TextStyle(color: Colors.white60, fontSize: 14),
                        ),
                      ),
              ),
              const SizedBox(height: 16),
            ],
          ),
        );
      },
    );
  }
}

class _StageMessage extends StatelessWidget {
  const _StageMessage({required this.message, required this.action});

  final String message;
  final Widget action;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white70, fontSize: 16),
            ),
            const SizedBox(height: 12),
            action,
          ],
        ),
      ),
    );
  }
}
