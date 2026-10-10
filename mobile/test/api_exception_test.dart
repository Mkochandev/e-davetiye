import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zarf/core/api/api_exception.dart';

DioException withResponse(int status, Object? data) {
  final request = RequestOptions(path: '/x');
  return DioException(
    requestOptions: request,
    response: Response(requestOptions: request, statusCode: status, data: data),
    type: DioExceptionType.badResponse,
  );
}

void main() {
  test('uses the first field error for validation failures', () {
    final e = ApiException.from(
      withResponse(422, {
        'message': 'Ad soyad alanı gerekli. (ve 1 hata daha)',
        'errors': {
          'name': ['Ad soyad alanı gerekli.'],
          'kvkk_accepted': ['Açık rıza onayı kabul edilmeli.'],
        },
      }),
    );

    expect(e.message, 'Ad soyad alanı gerekli.');
    expect(e.fieldErrors['kvkk_accepted'], 'Açık rıza onayı kabul edilmeli.');
  });

  test('keeps the server message and code for 402', () {
    final e = ApiException.from(
      withResponse(402, {'code': 'pack_required', 'message': 'Premium şablonlar için Etkinlik Paketi gerekiyor.'}),
    );

    expect(e.isPackRequired, isTrue);
    expect(e.message, 'Premium şablonlar için Etkinlik Paketi gerekiyor.');
  });

  test('falls back to Turkish messages', () {
    expect(ApiException.from(withResponse(500, '<html>')).message, ApiException.unexpectedMessage);
    expect(ApiException.from(withResponse(401, null)).isUnauthorized, isTrue);
    expect(
      ApiException.from(DioException.connectionTimeout(timeout: Duration.zero, requestOptions: RequestOptions()))
          .message,
      ApiException.networkMessage,
    );
  });
}
