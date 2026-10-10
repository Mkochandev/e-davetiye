import 'package:flutter_test/flutter_test.dart';
import 'package:zarf/features/auth/data/phone_input.dart';

String format(String input) =>
    PhoneInputFormatter().formatEditUpdate(TextEditingValue.empty, TextEditingValue(text: input)).text;

void main() {
  group('PhoneInput.validate', () {
    test('accepts exactly 10 digits', () {
      expect(PhoneInput.validate('532 111 22 33'), isNull);
      expect(PhoneInput.validate('5321112233'), isNull);
    });

    test('rejects fewer or more digits', () {
      expect(PhoneInput.validate(''), PhoneInput.invalidMessage);
      expect(PhoneInput.validate('532 111 22 3'), PhoneInput.invalidMessage);
      expect(PhoneInput.validate('53211122334'), PhoneInput.invalidMessage);
    });

    test('builds E.164 with the fixed +90', () {
      expect(PhoneInput.toE164('532 111 22 33'), '+905321112233');
    });
  });

  group('PhoneInputFormatter', () {
    test('groups digits as 532 111 22 33', () {
      expect(format('5321112233'), '532 111 22 33');
      expect(format('5321'), '532 1');
    });

    test('drops a leading 0, non-digits and digits beyond 10', () {
      expect(format('0532-111-22-33'), '532 111 22 33');
      expect(format('532111223399'), '532 111 22 33');
    });
  });
}
