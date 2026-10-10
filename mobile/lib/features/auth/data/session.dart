import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/storage/token_storage.dart';
import 'app_user.dart';
import 'auth_repository.dart';

sealed class SessionState {
  const SessionState();
}

/// Açılışta token kontrol ediliyor.
class SessionLoading extends SessionState {
  const SessionLoading();
}

/// Token var ama sunucuya ulaşılamadı; açılış ekranında "Tekrar dene".
class SessionUnreachable extends SessionState {
  const SessionUnreachable(this.message);
  final String message;
}

class SignedOut extends SessionState {
  const SignedOut();
}

class SignedIn extends SessionState {
  const SignedIn(this.user);
  final AppUser user;
}

class SessionController extends Notifier<SessionState> {
  @override
  SessionState build() {
    Future.microtask(restore);
    return const SessionLoading();
  }

  TokenStorage get _storage => ref.read(tokenStorageProvider);
  AuthRepository get _auth => ref.read(authRepositoryProvider);

  /// Uygulama açılışında oturum kontrolü.
  Future<void> restore() async {
    state = const SessionLoading();
    if (await _storage.read() == null) {
      state = const SignedOut();
      return;
    }

    try {
      state = SignedIn(await _auth.me());
    } on ApiException catch (e) {
      if (e.isUnauthorized) {
        await _storage.delete();
        state = const SignedOut();
      } else {
        state = SessionUnreachable(e.message);
      }
    }
  }

  Future<void> signIn(String token, AppUser user) async {
    await _storage.write(token);
    state = SignedIn(user);
  }

  void updateUser(AppUser user) => state = SignedIn(user);

  Future<void> signOut() async {
    try {
      await _auth.logout();
    } on ApiException {
      // Sunucuya ulaşılamasa da yerel oturum kapanır.
    }
    await _storage.delete();
    state = const SignedOut();
  }

  /// Sunucu token'ı reddetti (401).
  Future<void> expire() async {
    await _storage.delete();
    state = const SignedOut();
  }
}

final sessionProvider = NotifierProvider<SessionController, SessionState>(SessionController.new);
