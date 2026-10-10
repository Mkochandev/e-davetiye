import 'package:intl/intl.dart';

/// Tarihler sunucudan UTC gelir, Türkiye saatiyle (UTC+3, 2016'dan beri yaz saati yok) gösterilir.
abstract final class AppFormat {
  static const locale = 'tr_TR';

  static DateTime toIstanbul(DateTime utc) => utc.toUtc().add(const Duration(hours: 3));

  /// "12 Haziran 2027, Cumartesi · 19:00"
  static String eventDateTime(DateTime utc) {
    final local = toIstanbul(utc);
    return '${DateFormat('d MMMM y, EEEE', locale).format(local)} · ${DateFormat('HH:mm', locale).format(local)}';
  }

  /// "12 Haziran 2027"
  static String date(DateTime utc) => DateFormat('d MMMM y', locale).format(toIstanbul(utc));

  /// `5321112233` → `0532 111 22 33`
  static String nationalPhone(String e164) {
    final digits = e164.startsWith('+90') ? e164.substring(3) : e164;
    if (digits.length != 10) return e164;
    return '0${digits.substring(0, 3)} ${digits.substring(3, 6)} ${digits.substring(6, 8)} ${digits.substring(8)}';
  }
}
