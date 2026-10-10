import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/data/session.dart';
import '../config.dart';
import '../storage/token_storage.dart';

/// Tek Dio örneği: token'ı ekler; token'lı istekte 401 gelirse oturumu düşürür (router girişe döner).
final dioProvider = Provider<Dio>(createDio);

/// Testler aynı kurulumu sahte bir `HttpClientAdapter` ile kullanır.
Dio createDio(Ref ref) {
  final storage = ref.watch(tokenStorageProvider);

  final dio = Dio(BaseOptions(
    baseUrl: AppConfig.apiBaseUrl,
    connectTimeout: const Duration(seconds: 15),
    receiveTimeout: const Duration(seconds: 20),
    headers: {'Accept': 'application/json'},
  ));

  dio.interceptors.add(InterceptorsWrapper(
    onRequest: (options, handler) async {
      final token = await storage.read();
      if (token != null) options.headers['Authorization'] = 'Bearer $token';
      handler.next(options);
    },
    onError: (error, handler) {
      final hadToken = error.requestOptions.headers.containsKey('Authorization');
      if (error.response?.statusCode == 401 && hadToken) {
        ref.read(sessionProvider.notifier).expire();
      }
      handler.next(error);
    },
  ));

  return dio;
}
