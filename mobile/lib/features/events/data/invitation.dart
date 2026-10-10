import '../../../core/invitation/invitation_data.dart';
import '../../inbox/data/envelope.dart' show RsvpStatus;
import 'event.dart';

class Template {
  const Template({
    required this.id,
    required this.name,
    required this.slug,
    required this.isPremium,
    required this.config,
  });

  final int id;
  final String name;
  final String slug;
  final bool isPremium;
  final TemplateConfig config;

  factory Template.fromJson(Map<String, dynamic> json) => Template(
    id: json['id'] as int,
    name: json['name'] as String,
    slug: json['slug'] as String,
    isPremium: json['is_premium'] as bool? ?? false,
    config: TemplateConfig.fromJson(json['config'] as Map<String, dynamic>),
  );
}

/// Ücretsiz renkler (spec §4; sunucu `config/zarf.php` aynısını zorlar).
abstract final class FreeOptions {
  static const envelopeColors = {'sage', 'navy'};
  static const sealColors = {'bordo'};
}

/// Etkinliğin davetiyesi (`GET/PUT /events/{id}/invitation`).
class InvitationDraft {
  const InvitationDraft({
    this.id,
    required this.templateId,
    required this.texts,
    this.envelopeColor = 'sage',
    this.sealColor = 'bordo',
    this.sealText = '',
    this.rsvpDeadline,
    this.firstSentAt,
  });

  final int? id;
  final int? templateId;
  final InvitationTexts texts;
  final String envelopeColor;
  final String sealColor;
  final String sealText;

  /// `2027-06-01`
  final String? rsvpDeadline;
  final DateTime? firstSentAt;

  /// Yeni davetiye: etkinlik türüne göre varsayılan metinler (spec §11; sunucudaki InvitationDefaults ile aynı).
  factory InvitationDraft.defaults(AppEvent event) {
    final (label, message) = switch (event.type) {
      EventType.wedding => ('DÜĞÜN DAVETİ', 'düğünümüzde sizi de aramızda görmekten mutluluk duyarız.'),
      EventType.engagement => ('NİŞAN DAVETİ', 'nişanımızda sizi de aramızda görmekten mutluluk duyarız.'),
      EventType.henna => ('KINA GECESİ DAVETİ', 'kına gecemizde sizi de aramızda görmekten mutluluk duyarız.'),
      EventType.circumcision => (
        'SÜNNET DÜĞÜNÜ DAVETİ',
        'oğlumuzun sünnet düğününde sizi de aramızda görmekten mutluluk duyarız.',
      ),
      EventType.birthday => ('DOĞUM GÜNÜ DAVETİ', 'doğum günümüzde sizi de aramızda görmek isteriz.'),
      EventType.other => ('DAVET', 'sizi de aramızda görmekten mutluluk duyarız.'),
    };
    return InvitationDraft(
      templateId: null,
      texts: InvitationTexts(label: label, names: event.title, message: message),
    );
  }

  InvitationDraft copyWith({
    int? templateId,
    InvitationTexts? texts,
    String? envelopeColor,
    String? sealColor,
    String? sealText,
    String? rsvpDeadline,
    bool clearDeadline = false,
  }) => InvitationDraft(
    id: id,
    templateId: templateId ?? this.templateId,
    texts: texts ?? this.texts,
    envelopeColor: envelopeColor ?? this.envelopeColor,
    sealColor: sealColor ?? this.sealColor,
    sealText: sealText ?? this.sealText,
    rsvpDeadline: clearDeadline ? null : (rsvpDeadline ?? this.rsvpDeadline),
    firstSentAt: firstSentAt,
  );

  factory InvitationDraft.fromJson(Map<String, dynamic> json) => InvitationDraft(
    id: json['id'] as int,
    templateId: (json['template'] as Map)['id'] as int,
    texts: InvitationTexts.fromJson(json['texts'] as Map<String, dynamic>),
    envelopeColor: json['envelope_color'] as String,
    sealColor: json['seal_color'] as String,
    sealText: json['seal_text'] as String? ?? '',
    rsvpDeadline: json['rsvp_deadline'] as String?,
    firstSentAt: json['first_sent_at'] == null ? null : DateTime.parse(json['first_sent_at'] as String),
  );

  Map<String, dynamic> toJson() => {
    'template_id': templateId,
    'texts': {'label': texts.label, 'intro': texts.intro, 'names': texts.names, 'message': texts.message},
    'envelope_color': envelopeColor,
    'seal_color': sealColor,
    'seal_text': sealText.isEmpty ? null : sealText,
    'rsvp_deadline': rsvpDeadline,
  };
}

enum RecipientChannel { inApp, link }

/// Davetli satırı (`GET /events/{id}/invitation/recipients`).
class Recipient {
  const Recipient({
    required this.id,
    this.name,
    required this.phone,
    this.isAppUser = false,
    this.channel,
    this.sentAt,
    this.openedAt,
    this.rsvpStatus = RsvpStatus.pending,
    this.guestCount,
    this.url,
    this.whatsappUrl,
  });

  final int id;
  final String? name;
  final String phone;
  final bool isAppUser;
  final RecipientChannel? channel;
  final DateTime? sentAt;
  final DateTime? openedAt;
  final RsvpStatus rsvpStatus;
  final int? guestCount;

  /// Yalnız linkle gönderilmiş davetlide.
  final String? url;
  final String? whatsappUrl;

  bool get isSent => sentAt != null;
  bool get isOpened => openedAt != null;

  factory Recipient.fromJson(Map<String, dynamic> json) => Recipient(
    id: json['id'] as int,
    name: json['name'] as String?,
    phone: json['phone'] as String,
    isAppUser: json['is_app_user'] as bool? ?? false,
    channel: switch (json['channel']) {
      'in_app' => RecipientChannel.inApp,
      'link' => RecipientChannel.link,
      _ => null,
    },
    sentAt: json['sent_at'] == null ? null : DateTime.parse(json['sent_at'] as String),
    openedAt: json['opened_at'] == null ? null : DateTime.parse(json['opened_at'] as String),
    rsvpStatus: RsvpStatus.parse(json['rsvp_status'] as String?),
    guestCount: json['guest_count'] as int?,
    url: json['url'] as String?,
    whatsappUrl: json['whatsapp_url'] as String?,
  );
}

/// `POST /send` sonucu: yalnız bu çağrıda gönderilenler.
class SendResult {
  const SendResult({required this.inAppCount, required this.linkRecipients});

  final int inAppCount;
  final List<LinkRecipient> linkRecipients;

  factory SendResult.fromJson(Map<String, dynamic> json) => SendResult(
    inAppCount: json['in_app_count'] as int,
    linkRecipients: (json['link_recipients'] as List)
        .map((e) => LinkRecipient.fromJson(e as Map<String, dynamic>))
        .toList(),
  );
}

class LinkRecipient {
  const LinkRecipient({required this.id, this.name, required this.phone, required this.url, required this.whatsappUrl});

  final int id;
  final String? name;
  final String phone;
  final String url;
  final String whatsappUrl;

  factory LinkRecipient.fromJson(Map<String, dynamic> json) => LinkRecipient(
    id: json['id'] as int,
    name: json['name'] as String?,
    phone: json['phone'] as String,
    url: json['url'] as String,
    whatsappUrl: json['whatsapp_url'] as String,
  );
}

/// LCV sayıları (spec §6.4). Kovalar ayrık: açılmadı / bekliyor / katılıyor / katılamıyor.
class RsvpStats {
  const RsvpStats({
    this.sent = 0,
    this.opened = 0,
    this.attending = 0,
    this.attendingGuests = 0,
    this.notAttending = 0,
    this.pending = 0,
    this.unopened = 0,
  });

  final int sent;
  final int opened;
  final int attending;
  final int attendingGuests;
  final int notAttending;
  final int pending;
  final int unopened;

  factory RsvpStats.fromJson(Map<String, dynamic> json) => RsvpStats(
    sent: json['sent'] as int,
    opened: json['opened'] as int,
    attending: json['attending'] as int,
    attendingGuests: json['attending_guests'] as int,
    notAttending: json['not_attending'] as int,
    pending: json['pending'] as int,
    unopened: json['unopened'] as int,
  );
}
