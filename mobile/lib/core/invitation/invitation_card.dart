import 'package:flutter/material.dart';

import '../format.dart';
import 'invitation_data.dart';

/// Davetiye kartı (spec §11). Web `x-invitation-card` Blade bileşeniyle aynı sıra ve ölçüler:
/// label → intro → names → message → süsleme → tarih → gün ve saat → mekan adı → adres.
class InvitationCard extends StatelessWidget {
  const InvitationCard({
    super.key,
    required this.config,
    required this.texts,
    required this.startsAt,
    this.venueName,
    this.venueAddress,
  });

  final TemplateConfig config;
  final InvitationTexts texts;

  /// UTC; Türkiye saatiyle gösterilir.
  final DateTime startsAt;
  final String? venueName;
  final String? venueAddress;

  TextStyle _font(
    String family, {
    required double size,
    FontWeight weight = FontWeight.w400,
    Color? color,
    double height = 1.5,
  }) {
    return TextStyle(
      fontFamily: family,
      fontSize: size,
      fontWeight: weight,
      // Fraunces değişken font olarak gömülü.
      fontVariations: family == 'Fraunces' ? [FontVariation.weight(weight.value.toDouble())] : null,
      color: color ?? config.ink,
      height: height,
    );
  }

  @override
  Widget build(BuildContext context) {
    final c = config;
    final body = c.bodyFont;
    final frameColor = c.frameColor ?? Colors.transparent;

    Widget gap(double h) => SizedBox(height: h);

    final content = Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (texts.label.isNotEmpty)
          Text(
            texts.label,
            key: const Key('card-label'),
            textAlign: TextAlign.center,
            style: _font(body, size: 12, weight: FontWeight.w600, color: c.accent).copyWith(letterSpacing: 12 * 0.22),
          ),
        if (texts.intro.isNotEmpty) ...[
          gap(18),
          Text(
            texts.intro,
            key: const Key('card-intro'),
            textAlign: TextAlign.center,
            style: _font(body, size: 15, color: c.muted),
          ),
        ],
        gap(14),
        Text(
          texts.names,
          key: const Key('card-names'),
          textAlign: TextAlign.center,
          style: _font(c.displayFont, size: 38, weight: FontWeight.w500, height: 1.15),
        ),
        if (texts.message.isNotEmpty) ...[
          gap(14),
          Text(
            texts.message,
            key: const Key('card-message'),
            textAlign: TextAlign.center,
            style: _font(body, size: 15, color: c.muted),
          ),
        ],
        if (c.ornament == 'dot-rule')
          Padding(
            key: const Key('card-dot-rule'),
            padding: const EdgeInsets.symmetric(vertical: 24),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(width: 48, height: 1, color: c.accent.withValues(alpha: 0.6)),
                const SizedBox(width: 10),
                Container(
                  width: 6,
                  height: 6,
                  decoration: BoxDecoration(color: c.accent, shape: BoxShape.circle),
                ),
                const SizedBox(width: 10),
                Container(width: 48, height: 1, color: c.accent.withValues(alpha: 0.6)),
              ],
            ),
          )
        else
          gap(24),
        Text(
          AppFormat.date(startsAt),
          key: const Key('card-date'),
          textAlign: TextAlign.center,
          style: _font(c.displayFont, size: 24, weight: FontWeight.w500, height: 1.2),
        ),
        gap(4),
        Text(
          AppFormat.dayAndTime(startsAt),
          key: const Key('card-time'),
          textAlign: TextAlign.center,
          style: _font(body, size: 15, color: c.muted),
        ),
        if (venueName?.isNotEmpty ?? false) ...[
          gap(18),
          Text(
            venueName!,
            key: const Key('card-venue'),
            textAlign: TextAlign.center,
            style: _font(body, size: 16, weight: FontWeight.w600),
          ),
        ],
        if (venueAddress?.isNotEmpty ?? false) ...[
          gap(2),
          Text(
            venueAddress!,
            key: const Key('card-address'),
            textAlign: TextAlign.center,
            style: _font(body, size: 14, color: c.muted),
          ),
        ],
      ],
    );

    Widget inner = Container(
      key: Key('card-frame-${c.frame}'),
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(22, 40, 22, 36),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        border: c.frame == 'none' ? null : Border.all(color: frameColor),
      ),
      child: content,
    );

    if (c.frame == 'double') {
      inner = Stack(
        children: [
          inner,
          Positioned.fill(
            child: IgnorePointer(
              child: Container(
                margin: const EdgeInsets.all(5),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: frameColor),
                ),
              ),
            ),
          ),
        ],
      );
    }

    return Container(
      decoration: BoxDecoration(
        color: c.paper,
        borderRadius: BorderRadius.circular(22),
        boxShadow: const [BoxShadow(color: Color(0x1F17182A), blurRadius: 40, offset: Offset(0, 12))],
      ),
      clipBehavior: Clip.antiAlias,
      padding: const EdgeInsets.all(14),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          inner,
          if (c.ornament == 'top-band')
            Positioned(
              key: const Key('card-top-band'),
              top: -14,
              left: 0,
              right: 0,
              child: Center(
                child: Container(
                  width: 56,
                  height: 6,
                  decoration: BoxDecoration(
                    color: c.accent,
                    borderRadius: const BorderRadius.vertical(bottom: Radius.circular(4)),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
