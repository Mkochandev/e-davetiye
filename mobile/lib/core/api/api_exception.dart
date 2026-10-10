import 'package:dio/dio.dart';

/// API hatasının kullanıcıya gösterilecek Türkçe hali. Sunucunun `message` alanı varsa o kullanılır.
class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode, this.code, this.fieldErrors = const {}});

  final String message;
  final int? statusCode;

  /// Sunucunun makine okunur kodu, ör. `pack_required` (402).
  final String? code;

  /// 422 doğrulama hataları: alan → ilk mesaj.
  final Map<String, String> fieldErrors;

  static const networkMessage = 'Sunucuya ulaşılamadı. İnternet bağlantınızı kontrol edin.';
  static const unexpectedMessage = 'Beklenmeyen bir hata oluştu. Lütfen tekrar deneyin.';

  /// İsteği çalıştırır; her hatayı [ApiException]'a çevirir.
  static Future<T> guard<T>(Future<T> Function() request) async {
    try {
      return await request();
    } catch (error) {
      throw ApiException.from(error);
    }
  }

  bool get isUnauthorized => statusCode == 401;
  bool get isPackRequired => code == 'pack_required';

  factory ApiException.from(Object error) {
    if (error is ApiException) return error;
    if (error is! DioException) return const ApiException(unexpectedMessage);

    final response = error.response;
    if (response == null) {
      return const ApiException(networkMessage);
    }

    final status = response.statusCode;
    final data = response.data;
    String? serverMessage;
    String? code;
    final fieldErrors = <String, String>{};

    if (data is Map) {
      if (data['message'] is String) serverMessage = data['message'] as String;
      if (data['code'] is String) code = data['code'] as String;
      final errors = data['errors'];
      if (errors is Map) {
        errors.forEach((key, value) {
          if (value is List && value.isNotEmpty) fieldErrors['$key'] = '${value.first}';
        });
      }
    }

    // 422'de alan mesajı, Laravel'in "(ve 1 hata daha)" eklenmiş özetinden daha açıktır.
    final message =
        fieldErrors.values.firstOrNull ??
        serverMessage ??
        switch (status) {
          401 => 'Oturumunuz sona erdi. Lütfen tekrar giriş yapın.',
          404 => 'Kayıt bulunamadı.',
          429 => 'Çok fazla istek gönderildi. Lütfen biraz sonra tekrar deneyin.',
          _ => unexpectedMessage,
        };

    return ApiException(message, statusCode: status, code: code, fieldErrors: fieldErrors);
  }

  @override
  String toString() => message;
}
