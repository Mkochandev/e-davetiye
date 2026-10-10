import 'dart:ui';

import 'palette.dart';

/// Şablon config'i v1 (spec §11).
class TemplateConfig {
  const TemplateConfig({
    required this.paper,
    required this.ink,
    required this.muted,
    required this.accent,
    required this.frame,
    this.frameColor,
    required this.displayFont,
    required this.bodyFont,
    required this.ornament,
  });

  final Color paper;
  final Color ink;
  final Color muted;
  final Color accent;

  /// `none` | `single` | `double`
  final String frame;
  final Color? frameColor;
  final String displayFont;
  final String bodyFont;

  /// `none` | `dot-rule` | `top-band`
  final String ornament;

  factory TemplateConfig.fromJson(Map<String, dynamic> json) => TemplateConfig(
    paper: Palette.hex(json['paper'] as String),
    ink: Palette.hex(json['ink'] as String),
    muted: Palette.hex(json['muted'] as String),
    accent: Palette.hex(json['accent'] as String),
    frame: json['frame'] as String? ?? 'none',
    frameColor: json['frame_color'] is String ? Palette.hex(json['frame_color'] as String) : null,
    displayFont: json['display_font'] as String? ?? 'Fraunces',
    bodyFont: json['body_font'] as String? ?? 'Plus Jakarta Sans',
    ornament: json['ornament'] as String? ?? 'none',
  );
}

class InvitationTexts {
  const InvitationTexts({this.label = '', this.intro = '', this.names = '', this.message = ''});

  final String label;
  final String intro;
  final String names;
  final String message;

  factory InvitationTexts.fromJson(Map<String, dynamic> json) => InvitationTexts(
    label: (json['label'] as String? ?? '').trim(),
    intro: (json['intro'] as String? ?? '').trim(),
    names: (json['names'] as String? ?? '').trim(),
    message: (json['message'] as String? ?? '').trim(),
  );
}
