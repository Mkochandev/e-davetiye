import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import 'app_user.dart';

class AuthRepository {
  AuthRepository(this._dio);

  final Dio _dio;

  /// `phone`: E.164 (`+905321112233`).
  Future<void> requestOtp(String phone) => ApiException.guard(() => _dio.post('/auth/otp', data: {'phone': phone}));

  Future<({String token, AppUser user})> verify(String phone, String code) async {
    final response = await ApiException.guard(
      () => _dio.post('/auth/verify', data: {'phone': phone, 'code': code, 'device_name': _deviceName}),
    );
    final data = response.data as Map<String, dynamic>;
    return (token: data['token'] as String, user: AppUser.fromJson(data['user'] as Map<String, dynamic>));
  }

  Future<AppUser> me() async {
    final response = await ApiException.guard(() => _dio.get('/me'));
    return AppUser.fromJson(response.data as Map<String, dynamic>);
  }

  Future<AppUser> completeProfile(String name) async {
    final response = await ApiException.guard(() => _dio.patch('/me', data: {'name': name, 'kvkk_accepted': true}));
    return AppUser.fromJson(response.data as Map<String, dynamic>);
  }

  Future<void> logout() => ApiException.guard(() => _dio.post('/auth/logout'));

  static String get _deviceName => switch (defaultTargetPlatform) {
    TargetPlatform.iOS => 'iOS',
    TargetPlatform.android => 'Android',
    _ => 'Mobil',
  };
}

final authRepositoryProvider = Provider<AuthRepository>((ref) => AuthRepository(ref.watch(dioProvider)));
