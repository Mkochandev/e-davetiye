import 'package:flutter/services.dart';

/// M2 telefon alanı: `+90` sabit, kullanıcı 10 hane girer. Asıl normalizasyon sunucuda (`PhoneNumber`).
abstract final class PhoneInput {
  static const length = 10;
  static const invalidMessage = 'Telefon numarası 10 haneli olmalı.';
  static const notMobileMessage = 'Cep telefonu numarası 5 ile başlamalı.';

  static String digits(String text) => text.replaceAll(RegExp(r'\D'), '');

  /// Geçerliyse `null`, değilse Türkçe hata. Giriş kodu SMS'le gittiği için yalnız cep numarası (5XX).
  static String? validate(String text) {
    final d = digits(text);
    if (d.length != length) return invalidMessage;
    if (!d.startsWith('5')) return notMobileMessage;
    return null;
  }

  /// `532 111 22 33` → `+905321112233`
  static String toE164(String text) => '+90${digits(text)}';
}

/// Yalnız rakam, en fazla 10 hane, `532 111 22 33` biçiminde gruplar. Başa yazılan 0 atılır.
class PhoneInputFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) {
    var digits = PhoneInput.digits(newValue.text);
    if (digits.startsWith('0')) digits = digits.substring(1);
    if (digits.length > PhoneInput.length) digits = digits.substring(0, PhoneInput.length);

    final buffer = StringBuffer();
    for (var i = 0; i < digits.length; i++) {
      if (i == 3 || i == 6 || i == 8) buffer.write(' ');
      buffer.write(digits[i]);
    }
    final text = buffer.toString();
    return TextEditingValue(text: text, selection: TextSelection.collapsed(offset: text.length));
  }
}
