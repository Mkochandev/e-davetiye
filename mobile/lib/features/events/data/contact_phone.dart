import 'dart:convert';

import 'package:crypto/crypto.dart';

/// Rehberdeki numarayı E.164'e çevirir ve hash'ler. Sunucunun `PhoneNumber` servisi asıl normalizasyonu
/// yapar; burada yalnız `/contacts/match` için aynı E.164 metnini üretmek gerekir (hash'ler eşleşsin).
abstract final class ContactPhone {
  /// Faz 1 yalnız Türkiye numaraları: `0532 111 22 33`, `+90 (532) 111-22-33`, `0090532...`, `532...` → `+905321112233`.
  /// Türkiye dışı ya da eksik numara → `null`.
  static String? toE164(String raw) {
    final trimmed = raw.trim();
    var digits = trimmed.replaceAll(RegExp(r'\D'), '');
    final international = trimmed.startsWith('+') || digits.startsWith('00');

    if (digits.startsWith('00')) digits = digits.substring(2);

    if (international) {
      if (!digits.startsWith('90')) return null;
      digits = digits.substring(2);
    } else if (digits.length == 12 && digits.startsWith('90')) {
      digits = digits.substring(2);
    } else if (digits.length == 11 && digits.startsWith('0')) {
      digits = digits.substring(1);
    }

    // Türkiye ulusal numarası 10 hane, 0 ile başlamaz (2xx–5xx, 8xx).
    if (digits.length != 10 || digits.startsWith('0') || digits.startsWith('1')) return null;
    return '+90$digits';
  }

  /// SHA-256 hex (küçük harf) — sunucu `hash('sha256', $e164)` ile aynı.
  static String hash(String e164) => sha256.convert(utf8.encode(e164)).toString();
}
