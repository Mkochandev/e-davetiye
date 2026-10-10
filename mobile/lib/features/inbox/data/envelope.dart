import '../../../core/invitation/invitation_data.dart';

enum RsvpStatus {
  pending('pending'),
  attending('attending'),
  notAttending('not_attending');

  const RsvpStatus(this.value);
  final String value;

  static RsvpStatus parse(String? value) => values.firstWhere((s) => s.value == value, orElse: () => pending);
}

DateTime? _date(Object? v) => v is String ? DateTime.parse(v) : null;

/// Posta kutusu satırı (`GET /inbox`).
class Envelope {
  const Envelope({
    required this.id,
    required this.title,
    required this.startsAt,
    this.openedAt,
    required this.rsvpStatus,
    required this.envelopeColor,
    required this.sealColor,
    this.sealText,
    required this.cancelled,
  });

  final int id;
  final String title;
  final DateTime startsAt;
  final DateTime? openedAt;
  final RsvpStatus rsvpStatus;
  final String envelopeColor;
  final String sealColor;
  final String? sealText;
  final bool cancelled;

  bool get isOpened => openedAt != null;

  factory Envelope.fromJson(Map<String, dynamic> json) => Envelope(
    id: json['id'] as int,
    title: json['title'] as String,
    startsAt: DateTime.parse(json['starts_at'] as String),
    openedAt: _date(json['opened_at']),
    rsvpStatus: RsvpStatus.parse(json['rsvp_status'] as String?),
    envelopeColor: json['envelope_color'] as String,
    sealColor: json['seal_color'] as String,
    sealText: json['seal_text'] as String?,
    cancelled: json['cancelled'] as bool? ?? false,
  );
}

/// Davetiyeyi çizmek ve LCV vermek için her şey (`GET /inbox/{id}`).
class EnvelopeDetail {
  const EnvelopeDetail({
    required this.id,
    required this.config,
    required this.texts,
    required this.title,
    required this.startsAt,
    this.endsAt,
    this.venueName,
    this.venueAddress,
    this.latitude,
    this.longitude,
    required this.cancelled,
    required this.envelopeColor,
    required this.sealColor,
    this.sealText,
    this.openedAt,
    required this.rsvpStatus,
    this.guestCount,
    this.respondedAt,
    this.rsvpDeadline,
    required this.rsvpOpen,
  });

  final int id;
  final TemplateConfig config;
  final InvitationTexts texts;
  final String title;
  final DateTime startsAt;
  final DateTime? endsAt;
  final String? venueName;
  final String? venueAddress;
  final double? latitude;
  final double? longitude;
  final bool cancelled;
  final String envelopeColor;
  final String sealColor;
  final String? sealText;
  final DateTime? openedAt;
  final RsvpStatus rsvpStatus;
  final int? guestCount;
  final DateTime? respondedAt;

  /// `2027-06-01` (Türkiye günü), yoksa `null`.
  final String? rsvpDeadline;

  /// Sunucunun kararı: iptal yok, etkinlik başlamadı ve son tarih geçmedi.
  final bool rsvpOpen;

  bool get hasLocation => latitude != null && longitude != null;

  factory EnvelopeDetail.fromJson(Map<String, dynamic> json) {
    final event = json['event'] as Map<String, dynamic>;
    final template = json['template'] as Map<String, dynamic>;
    return EnvelopeDetail(
      id: json['id'] as int,
      config: TemplateConfig.fromJson(template['config'] as Map<String, dynamic>),
      texts: InvitationTexts.fromJson(json['texts'] as Map<String, dynamic>),
      title: event['title'] as String,
      startsAt: DateTime.parse(event['starts_at'] as String),
      endsAt: _date(event['ends_at']),
      venueName: event['venue_name'] as String?,
      venueAddress: event['venue_address'] as String?,
      latitude: (event['latitude'] as num?)?.toDouble(),
      longitude: (event['longitude'] as num?)?.toDouble(),
      cancelled: event['cancelled'] as bool? ?? false,
      envelopeColor: json['envelope_color'] as String,
      sealColor: json['seal_color'] as String,
      sealText: json['seal_text'] as String?,
      openedAt: _date(json['opened_at']),
      rsvpStatus: RsvpStatus.parse(json['rsvp_status'] as String?),
      guestCount: json['guest_count'] as int?,
      respondedAt: _date(json['responded_at']),
      rsvpDeadline: json['rsvp_deadline'] as String?,
      rsvpOpen: json['rsvp_open'] as bool? ?? false,
    );
  }
}
