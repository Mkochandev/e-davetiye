import 'package:flutter/material.dart';

import '../../../core/widgets/common.dart';

// Sekmelerin içeriği sonraki adımlarda gelecek (M6 Adım 6, M9 Adım 7, M17 Adım 9).

class InboxTab extends StatelessWidget {
  const InboxTab({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Posta kutusu')),
      body: const EmptyState(
        icon: Icons.mail_outline,
        title: 'Posta kutun boş',
        message: 'Sana gönderilen davetiyeler burada zarf olarak görünecek.',
      ),
    );
  }
}

class EventsTab extends StatelessWidget {
  const EventsTab({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Etkinliklerim')),
      body: const EmptyState(
        icon: Icons.event_outlined,
        title: 'Henüz etkinliğin yok',
        message: 'Salonunun eklediği ya da senin oluşturduğun etkinlikler burada görünecek.',
      ),
    );
  }
}

class ProfileTab extends StatelessWidget {
  const ProfileTab({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Profil')),
      body: const EmptyState(
        icon: Icons.person_outline,
        title: 'Profil',
        message: 'Profil ayarların burada olacak.',
      ),
    );
  }
}
