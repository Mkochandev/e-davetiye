import 'package:flutter_contacts/flutter_contacts.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'contact_phone.dart';

/// Rehberden okunan kişi; numara E.164'e çevrilmiş.
class PhoneContact {
  const PhoneContact({required this.name, required this.phone});

  final String name;
  final String phone;

  String get hash => ContactPhone.hash(phone);
}

enum ContactsAccess { granted, denied, permanentlyDenied }

/// Rehber erişimi; testlerde sahtesi kullanılır.
abstract interface class ContactsSource {
  Future<ContactsAccess> requestAccess();

  /// Türkiye numarası olan kişiler; bir kişinin birden çok numarası ayrı satır olur, aynı numara bir kez.
  Future<List<PhoneContact>> load();
}

class DeviceContactsSource implements ContactsSource {
  @override
  Future<ContactsAccess> requestAccess() async {
    return switch (await FlutterContacts.permissions.request(PermissionType.read)) {
      PermissionStatus.granted || PermissionStatus.limited => ContactsAccess.granted,
      PermissionStatus.permanentlyDenied || PermissionStatus.restricted => ContactsAccess.permanentlyDenied,
      _ => ContactsAccess.denied,
    };
  }

  @override
  Future<List<PhoneContact>> load() async {
    final contacts = await FlutterContacts.getAll(properties: {ContactProperty.name, ContactProperty.phone});
    final seen = <String>{};
    final result = <PhoneContact>[];
    for (final c in contacts) {
      for (final p in c.phones) {
        final e164 = ContactPhone.toE164(p.normalizedNumber ?? p.number) ?? ContactPhone.toE164(p.number);
        if (e164 != null && seen.add(e164)) {
          result.add(PhoneContact(name: (c.displayName ?? '').trim(), phone: e164));
        }
      }
    }
    result.sort((a, b) => a.name.toLowerCase().compareTo(b.name.toLowerCase()));
    return result;
  }
}

final contactsSourceProvider = Provider<ContactsSource>((ref) => DeviceContactsSource());
