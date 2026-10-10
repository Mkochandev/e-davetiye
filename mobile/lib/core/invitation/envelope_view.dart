import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import 'palette.dart';

/// Zarf (spec §15): gövde, kapak (× 0,88), iç (× 0,62), mühür. Web `x-envelope` ile aynı geometri.
///
/// [progress] 0 = kapalı, 1 = açık: kapak `rotateX` ile açılır, mühür küçülüp kaybolur, kart yukarı kayar.
class EnvelopeView extends StatelessWidget {
  const EnvelopeView({
    super.key,
    required this.color,
    required this.sealColor,
    this.sealText,
    this.width = 320,
    this.progress = 0,
    this.onSealTap,
    this.letter,
  });

  /// `sage`, `navy`, `blush`, `sand`
  final String color;

  /// `bordo`, `gold`, `ink`, `forest`
  final String sealColor;
  final String? sealText;
  final double width;
  final double progress;
  final VoidCallback? onSealTap;

  /// Zarfın içindeki kartın üstünde görünen içerik (ör. isimler).
  final Widget? letter;

  static const flapHeight = 0.58;

  @override
  Widget build(BuildContext context) {
    final height = width * 2 / 3;
    final body = Palette.envelope(color);
    final flap = Palette.scale(body, Palette.flapFactor);
    final inner = Palette.scale(body, Palette.innerFactor);
    final seal = Palette.seal(sealColor);

    double phase(double start, double end) => ((progress - start) / (end - start)).clamp(0.0, 1.0);
    final flapT = Curves.easeInOut.transform(phase(0, 0.45));
    final sealT = Curves.easeOut.transform(phase(0, 0.25));
    final letterT = Curves.easeOutCubic.transform(phase(0.35, 1));

    final flapWidget = Positioned(
      key: const Key('envelope-flap'),
      left: 0,
      right: 0,
      top: 0,
      height: height * flapHeight,
      child: Transform(
        alignment: Alignment.topCenter,
        transform: Matrix4.identity()
          ..setEntry(3, 2, 0.0012)
          ..rotateX(-math.pi * flapT),
        child: CustomPaint(painter: _FlapPainter(flap)),
      ),
    );
    final flapOpen = flapT > 0.5;

    final letterHeight = height * (1 - 0.07 - 0.06);
    final sealSize = width * 0.2;

    return SizedBox(
      width: width,
      height: height,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(color: inner, borderRadius: BorderRadius.circular(10)),
            ),
          ),
          // Kapak yarıdan fazla açılınca kartın arkasına geçer.
          if (flapOpen) flapWidget,
          Positioned(
            left: width * 0.06,
            right: width * 0.06,
            top: height * 0.07,
            height: letterHeight,
            child: Transform.translate(
              key: const Key('envelope-letter'),
              offset: Offset(0, -letterHeight * 0.58 * letterT),
              child: Container(
                alignment: Alignment.center,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(6)),
                child: letter,
              ),
            ),
          ),
          Positioned.fill(child: CustomPaint(painter: _PocketPainter(body))),
          if (!flapOpen) flapWidget,
          if (sealT < 1)
            Positioned(
              left: (width - sealSize) / 2,
              top: height * flapHeight - sealSize / 2,
              width: sealSize,
              height: sealSize,
              child: Opacity(
                opacity: 1 - sealT,
                child: Transform.scale(
                  scale: 1 - 0.4 * sealT,
                  child: _Seal(color: seal, text: sealText, size: sealSize, onTap: onSealTap),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _Seal extends StatelessWidget {
  const _Seal({required this.color, required this.text, required this.size, this.onTap});

  final Color color;
  final String? text;
  final double size;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final seal = Container(
      decoration: BoxDecoration(
        color: color,
        shape: BoxShape.circle,
        boxShadow: const [BoxShadow(color: Color(0x4D000000), blurRadius: 16, offset: Offset(0, 6))],
      ),
      padding: EdgeInsets.all(size * 0.09),
      child: DecoratedBox(
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(color: Colors.white.withValues(alpha: 0.4), width: 1.5),
        ),
        child: Center(
          child: Text(
            text ?? '',
            maxLines: 1,
            style: AppFonts.displayStyle(size: size * 0.3, weight: FontWeight.w600, color: Colors.white),
          ),
        ),
      ),
    );

    if (onTap == null) return seal;
    return Semantics(
      button: true,
      label: 'Zarfı aç',
      child: GestureDetector(key: const Key('envelope-seal'), onTap: onTap, child: seal),
    );
  }
}

class _FlapPainter extends CustomPainter {
  _FlapPainter(this.color);
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    canvas.save();
    canvas.clipRRect(RRect.fromRectAndCorners(
      Offset.zero & size,
      topLeft: const Radius.circular(10),
      topRight: const Radius.circular(10),
    ));
    final path = Path()
      ..moveTo(0, 0)
      ..lineTo(size.width, 0)
      ..lineTo(size.width / 2, size.height)
      ..close();
    canvas.drawPath(path, Paint()..color = color);
    canvas.restore();
  }

  @override
  bool shouldRepaint(_FlapPainter old) => old.color != color;
}

/// Önden cep: üstte V kesik bırakan beşgen (web: `polygon(0 0, 50% 54%, 100% 0, 100% 100%, 0 100%)`).
class _PocketPainter extends CustomPainter {
  _PocketPainter(this.color);
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    canvas.save();
    canvas.clipRRect(RRect.fromRectAndRadius(Offset.zero & size, const Radius.circular(10)));
    final path = Path()
      ..moveTo(0, 0)
      ..lineTo(size.width / 2, size.height * 0.54)
      ..lineTo(size.width, 0)
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();
    canvas.drawPath(path, Paint()..color = color);
    canvas.restore();
  }

  @override
  bool shouldRepaint(_PocketPainter old) => old.color != color;
}
