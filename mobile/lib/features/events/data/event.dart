enum EventType {
  wedding('wedding', 'Düğün'),
  engagement('engagement', 'Nişan'),
  henna('henna', 'Kına gecesi'),
  circumcision('circumcision', 'Sünnet düğünü'),
  birthday('birthday', 'Doğum günü'),
  other('other', 'Diğer');

  const EventType(this.value, this.label);
  final String value;
  final String label;

  static EventType parse(String? value) => values.firstWhere((t) => t.value == value, orElse: () => other);
}

enum InvitationStatus {
  none,
  draft,
  sent;

  static InvitationStatus parse(String? value) => values.firstWhere((s) => s.name == value, orElse: () => none);
}

/// Kullanıcının sahibi olduğu etkinlik (`GET /events`). Ücret bilgisi asla gelmez.
class AppEvent {
  const AppEvent({
    required this.id,
    required this.type,
    required this.title,
    required this.startsAt,
    this.endsAt,
    required this.venueName,
    this.venueAddress,
    this.latitude,
    this.longitude,
    this.salonName,
    this.lockedFields = const [],
    this.shareHeadcountWithSalon = true,
    this.hasPack = false,
    this.recipientLimit = 30,
    this.cancelled = false,
    this.invitationStatus = InvitationStatus.none,
  });

  final int id;
  final EventType type;
  final String title;
  final DateTime startsAt;
  final DateTime? endsAt;
  final String venueName;
  final String? venueAddress;
  final double? latitude;
  final double? longitude;

  /// Salonun eklediği etkinlikte salon adı.
  final String? salonName;
  final List<String> lockedFields;
  final bool shareHeadcountWithSalon;
  final bool hasPack;
  final int recipientLimit;
  final bool cancelled;
  final InvitationStatus invitationStatus;

  bool get fromSalon => salonName != null;

  factory AppEvent.fromJson(Map<String, dynamic> json) => AppEvent(
    id: json['id'] as int,
    type: EventType.parse(json['type'] as String?),
    title: json['title'] as String,
    startsAt: DateTime.parse(json['starts_at'] as String),
    endsAt: json['ends_at'] == null ? null : DateTime.parse(json['ends_at'] as String),
    venueName: json['venue_name'] as String? ?? '',
    venueAddress: json['venue_address'] as String?,
    latitude: (json['latitude'] as num?)?.toDouble(),
    longitude: (json['longitude'] as num?)?.toDouble(),
    salonName: (json['salon'] as Map?)?['name'] as String?,
    lockedFields: (json['locked_fields'] as List? ?? const []).cast<String>(),
    shareHeadcountWithSalon: json['share_headcount_with_salon'] as bool? ?? true,
    hasPack: json['has_pack'] as bool? ?? false,
    recipientLimit: json['recipient_limit'] as int? ?? 30,
    cancelled: json['cancelled'] as bool? ?? false,
    invitationStatus: InvitationStatus.parse(json['invitation_status'] as String?),
  );
}

/// M10 formundan gelen alanlar. Saatler Türkiye saatiyle girilir, `+03:00` ile gönderilir.
class EventDraft {
  const EventDraft({
    required this.type,
    required this.title,
    required this.startsAtLocal,
    required this.venueName,
    this.venueAddress,
  });

  final EventType type;
  final String title;

  /// Türkiye saatiyle (saat dilimi bilgisi yok sayılır).
  final DateTime startsAtLocal;
  final String venueName;
  final String? venueAddress;

  static String istanbulIso(DateTime local) {
    String two(int n) => n.toString().padLeft(2, '0');
    return '${local.year}-${two(local.month)}-${two(local.day)}T${two(local.hour)}:${two(local.minute)}:00+03:00';
  }

  Map<String, dynamic> toJson() => {
    'type': type.value,
    'title': title,
    'starts_at': istanbulIso(startsAtLocal),
    'venue_name': venueName,
    if (venueAddress != null && venueAddress!.isNotEmpty) 'venue_address': venueAddress,
  };
}
