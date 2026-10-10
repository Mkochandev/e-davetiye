import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

/// Tanıtım ekranındaki kapalı zarf görseli (spec §15 renkleri). Açılır `EnvelopeView` Adım 6'da.
class EnvelopeArt extends StatelessWidget {
  const EnvelopeArt({super.key, this.width = 280, this.sealText = 'Z'});

  final double width;
  final String sealText;

  static const _body = Color(0xFFB7C4B1); // sage
  static const _seal = Color(0xFF8A1F3D); // bordo

  /// Kapak = zarf × 0,88; iç = zarf × 0,62.
  static Color shade(Color c, double f) => Color.from(alpha: c.a, red: c.r * f, green: c.g * f, blue: c.b * f);

  @override
  Widget build(BuildContext context) {
    final height = width * 2 / 3;
    final sealSize = width * 0.2;

    return SizedBox(
      width: width,
      height: height,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned.fill(child: CustomPaint(painter: _EnvelopePainter(_body, shade(_body, 0.88)))),
          Positioned(
            left: (width - sealSize) / 2,
            top: height * 0.58 - sealSize / 2,
            child: Container(
              width: sealSize,
              height: sealSize,
              decoration: BoxDecoration(
                color: _seal,
                shape: BoxShape.circle,
                boxShadow: const [BoxShadow(color: Color(0x4D000000), blurRadius: 16, offset: Offset(0, 6))],
              ),
              padding: EdgeInsets.all(sealSize * 0.09),
              child: DecoratedBox(
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white.withValues(alpha: 0.4), width: 1.5),
                ),
                child: Center(
                  child: Text(sealText, style: AppFonts.displayStyle(size: sealSize * 0.36, weight: FontWeight.w600, color: Colors.white)),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _EnvelopePainter extends CustomPainter {
  _EnvelopePainter(this.body, this.flap);

  final Color body;
  final Color flap;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = RRect.fromRectAndRadius(Offset.zero & size, const Radius.circular(10));
    canvas.save();
    canvas.clipRRect(rect);
    canvas.drawRect(Offset.zero & size, Paint()..color = body);
    final flapPath = Path()
      ..moveTo(0, 0)
      ..lineTo(size.width, 0)
      ..lineTo(size.width / 2, size.height * 0.58)
      ..close();
    canvas.drawPath(flapPath, Paint()..color = flap);
    canvas.restore();
  }

  @override
  bool shouldRepaint(_EnvelopePainter old) => old.body != body || old.flap != flap;
}
