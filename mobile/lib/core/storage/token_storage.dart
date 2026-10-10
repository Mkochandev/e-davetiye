import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Oturum token'ının saklandığı yer. Gerçekte Keychain / Keystore; testlerde bellek.
abstract interface class TokenStorage {
  Future<String?> read();
  Future<void> write(String token);
  Future<void> delete();
}

class SecureTokenStorage implements TokenStorage {
  SecureTokenStorage([FlutterSecureStorage? storage]) : _storage = storage ?? const FlutterSecureStorage();

  static const _key = 'auth_token';
  final FlutterSecureStorage _storage;

  @override
  Future<String?> read() => _storage.read(key: _key);

  @override
  Future<void> write(String token) => _storage.write(key: _key, value: token);

  @override
  Future<void> delete() => _storage.delete(key: _key);
}

class MemoryTokenStorage implements TokenStorage {
  MemoryTokenStorage([this.token]);

  String? token;

  @override
  Future<String?> read() async => token;

  @override
  Future<void> write(String token) async => this.token = token;

  @override
  Future<void> delete() async => token = null;
}

final tokenStorageProvider = Provider<TokenStorage>((ref) => SecureTokenStorage());
