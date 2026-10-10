/// Derleme zamanı ayarları. Yerel geliştirme:
/// `flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1`
abstract final class AppConfig {
  static const apiBaseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8000/api/v1');

  /// Web sayfalarının kökü (`/aydinlatma-metni` vb.): API adresinden `/api/v1` atılarak bulunur.
  static String get webBaseUrl => apiBaseUrl.replaceFirst(RegExp(r'/api/v1/?$'), '');

  static Uri webPage(String path) => Uri.parse('$webBaseUrl$path');
}
