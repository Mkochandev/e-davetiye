import 'package:add_2_calendar/add_2_calendar.dart' as cal;
import 'package:flutter/foundation.dart';
import 'package:url_launcher/url_launcher.dart';

import 'envelope.dart';

/// M8 "Takvime ekle" ve "Yol tarifi".
abstract final class InvitationActions {
  /// Bitiş saati yoksa takvimde 4 saatlik etkinlik olarak eklenir.
  static const defaultLength = Duration(hours: 4);

  static Future<bool> addToCalendar(EnvelopeDetail d) {
    final start = d.startsAt.toLocal();
    return cal.Add2Calendar.addEvent2Cal(cal.Event(
      title: d.title,
      description: d.texts.label.isEmpty ? null : d.texts.label,
      location: [d.venueName, d.venueAddress].whereType<String>().where((s) => s.isNotEmpty).join(', '),
      startDate: start,
      endDate: d.endsAt?.toLocal() ?? start.add(defaultLength),
    ));
  }

  /// iOS'ta Apple Haritalar, diğerlerinde Google Haritalar (uygulama yüklüyse o açılır).
  static Uri directionsUri(double lat, double lng, {TargetPlatform? platform}) {
    return switch (platform ?? defaultTargetPlatform) {
      TargetPlatform.iOS => Uri.https('maps.apple.com', '/', {'daddr': '$lat,$lng'}),
      _ => Uri.https('www.google.com', '/maps/dir/', {'api': '1', 'destination': '$lat,$lng'}),
    };
  }

  static Future<bool> openDirections(EnvelopeDetail d) =>
      launchUrl(directionsUri(d.latitude!, d.longitude!), mode: LaunchMode.externalApplication);
}
