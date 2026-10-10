/// M13 `x/30` sayacı. Sınırı sunucu zorlar (`402 pack_required`); burada yalnız gösterilir ve paywall erken açılır.
class RecipientLimit {
  const RecipientLimit({required this.existing, required this.selected, required this.limit, required this.hasPack});

  /// Davetiyede zaten olan davetliler.
  final int existing;

  /// Bu ekranda seçilen yeni kişiler.
  final int selected;
  final int limit;
  final bool hasPack;

  int get total => existing + selected;
  int get remaining => (limit - existing).clamp(0, limit);
  bool get exceeded => total > limit;

  /// Paketsiz etkinlikte sınır aşılınca paywall; paketliyse teknik üst sınır hatası.
  bool get needsPack => exceeded && !hasPack;

  /// "12/30"; paketliyken sınır gösterilmez.
  String get label => hasPack ? '$total davetli' : '$total/$limit';
}
