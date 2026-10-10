import 'envelope.dart';

/// M8 LCV formu: seçim + kişi sayısı. Sunucu aynı kuralları yeniden doğrular.
class RsvpDraft {
  const RsvpDraft({this.status, this.guestCount = 1});

  static const minGuests = 1;
  static const maxGuests = 10;

  final RsvpStatus? status;
  final int guestCount;

  factory RsvpDraft.from(EnvelopeDetail detail) => RsvpDraft(
        status: detail.rsvpStatus == RsvpStatus.pending ? null : detail.rsvpStatus,
        guestCount: detail.guestCount ?? 1,
      );

  RsvpDraft copyWith({RsvpStatus? status, int? guestCount}) =>
      RsvpDraft(status: status ?? this.status, guestCount: guestCount ?? this.guestCount);

  bool get canDecrement => guestCount > minGuests;
  bool get canIncrement => guestCount < maxGuests;

  RsvpDraft increment() => copyWith(guestCount: (guestCount + 1).clamp(minGuests, maxGuests));
  RsvpDraft decrement() => copyWith(guestCount: (guestCount - 1).clamp(minGuests, maxGuests));

  /// Geçerliyse `null`.
  String? validate() {
    if (status == null) return 'Katılım durumunu seç.';
    if (status == RsvpStatus.attending && (guestCount < minGuests || guestCount > maxGuests)) {
      return 'Kişi sayısı $minGuests ile $maxGuests arasında olmalı.';
    }
    return null;
  }
}
