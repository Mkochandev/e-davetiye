import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:zarf/app.dart';
import 'package:zarf/core/api/api_client.dart';
import 'package:zarf/core/storage/token_storage.dart';

class FakeResponse {
  const FakeResponse(this.status, [this.body]);
  final int status;
  final Object? body;
}

typedef FakeHandler = FakeResponse Function(RequestOptions request, Map<String, dynamic> body);

/// Sunucu yerine geçen adaptör: `'POST /auth/otp'` → yanıt. Gelen istekleri de kaydeder.
class FakeApi implements HttpClientAdapter {
  final Map<String, FakeHandler> routes = {};
  final List<RequestOptions> requests = [];

  void on(String route, FakeHandler handler) => routes[route] = handler;

  List<String> get calls => requests.map((r) => '${r.method} ${r.path}').toList();

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    requests.add(options);
    final handler = routes['${options.method} ${options.path}'];
    final body = options.data is Map ? Map<String, dynamic>.from(options.data as Map) : <String, dynamic>{};
    final response = handler?.call(options, body) ?? const FakeResponse(404, {'message': 'Kayıt bulunamadı.'});

    return ResponseBody.fromString(
      response.body == null ? '' : jsonEncode(response.body),
      response.status,
      headers: {
        Headers.contentTypeHeader: ['application/json'],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

Map<String, dynamic> userJson({String? name, bool consented = false}) => {
      'id': 1,
      'phone': '+905321112233',
      'name': name,
      'kvkk_accepted_at': consented ? '2026-10-10T10:00:00.000000Z' : null,
      'kvkk_version': consented ? '2026-10' : null,
    };

/// Uygulamayı sahte API ve bellek içi token deposuyla açar.
Future<void> pumpApp(WidgetTester tester, FakeApi api, {String? token}) async {
  await tester.pumpWidget(ProviderScope(
    overrides: [
      tokenStorageProvider.overrideWithValue(MemoryTokenStorage(token)),
      dioProvider.overrideWith((ref) => createDio(ref)..httpClientAdapter = api),
    ],
    child: const ZarfApp(),
  ));
  await tester.pumpAndSettle();
}

/// Sunucuya ulaşılamadı (yanıt yok).
class DioUnreachable extends DioException {
  DioUnreachable(RequestOptions request)
      : super(requestOptions: request, type: DioExceptionType.connectionError, message: 'offline');
}
