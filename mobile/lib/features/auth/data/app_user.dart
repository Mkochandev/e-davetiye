class AppUser {
  const AppUser({required this.id, required this.phone, this.name, this.kvkkAcceptedAt});

  final int id;
  final String phone;
  final String? name;
  final DateTime? kvkkAcceptedAt;

  /// M4 (profil tamamlama) ad ya da açık rıza eksikse gösterilir; `is_new`'e bakılmaz.
  bool get isProfileComplete => (name?.trim().isNotEmpty ?? false) && kvkkAcceptedAt != null;

  factory AppUser.fromJson(Map<String, dynamic> json) => AppUser(
        id: json['id'] as int,
        phone: json['phone'] as String,
        name: json['name'] as String?,
        kvkkAcceptedAt: json['kvkk_accepted_at'] == null ? null : DateTime.parse(json['kvkk_accepted_at'] as String),
      );
}
