import 'package:flutter/material.dart';

import '../../../core/widgets/common.dart';

// Sekmelerin içeriği sonraki adımlarda gelecek (M17 Adım 9).

class ProfileTab extends StatelessWidget {
  const ProfileTab({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Profil')),
      body: const EmptyState(icon: Icons.person_outline, title: 'Profil', message: 'Profil ayarların burada olacak.'),
    );
  }
}
