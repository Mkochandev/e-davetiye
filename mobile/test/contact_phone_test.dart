import 'package:flutter_test/flutter_test.dart';
import 'package:zarf/features/events/data/contact_phone.dart';
import 'package:zarf/features/events/data/recipient_limit.dart';

void main() {
  group('ContactPhone.toE164', () {
    test('normalizes the ways Turkish numbers are written in contacts', () {
      for (final raw in [
        '0532 111 22 33',
        '05321112233',
        '532 111 22 33',
        '+90 532 111 22 33',
        '+90 (532) 111-22-33',
        '0090 532 111 22 33',
        '90 532 111 2233',
        ' +905321112233 ',
      ]) {
        expect(ContactPhone.toE164(raw), '+905321112233', reason: raw);
      }
      expect(ContactPhone.toE164('0342 111 22 33'), '+903421112233');
    });

    test('rejects foreign, short and service numbers', () {
      for (final raw in [
        '+49 151 1234 5678',
        '+1 415 555 0100',
        '532 111 22',
        '112',
        '444 1 444',
        '0 000 000 00 00',
        '',
      ]) {
        expect(ContactPhone.toE164(raw), isNull, reason: raw);
      }
    });
  });

  test('hashes the E.164 text with SHA-256 like the server', () {
    // PHP: hash('sha256', '+905321112233')
    expect(ContactPhone.hash('+905321112233'), 'e10e0c8bb9f1ab595a64c1984c2b9ffa5f3029c487db507e4e3b9541a94d8b6b');
  });

  group('RecipientLimit', () {
    test('counts existing plus selected against the free limit', () {
      const l = RecipientLimit(existing: 25, selected: 5, limit: 30, hasPack: false);
      expect(l.label, '30/30');
      expect(l.exceeded, isFalse);
      expect(l.needsPack, isFalse);
      expect(l.remaining, 5);
    });

    test('asks for the pack once the 31st guest is selected', () {
      const l = RecipientLimit(existing: 25, selected: 6, limit: 30, hasPack: false);
      expect(l.label, '31/30');
      expect(l.needsPack, isTrue);
    });

    test('hides the limit with a pack and never asks for it', () {
      const l = RecipientLimit(existing: 40, selected: 2, limit: 1000, hasPack: true);
      expect(l.label, '42 davetli');
      expect(l.needsPack, isFalse);
      expect(const RecipientLimit(existing: 1000, selected: 1, limit: 1000, hasPack: true).needsPack, isFalse);
    });
  });
}
