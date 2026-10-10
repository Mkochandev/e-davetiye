import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/data/session.dart';
import '../../features/auth/ui/code_screen.dart';
import '../../features/auth/ui/phone_screen.dart';
import '../../features/auth/ui/profile_setup_screen.dart';
import '../../features/auth/ui/splash_screen.dart';
import '../../features/auth/ui/welcome_screen.dart';
import '../../features/events/ui/contact_picker_screen.dart';
import '../../features/events/ui/event_create_screen.dart';
import '../../features/events/ui/event_detail_screen.dart';
import '../../features/events/ui/events_screen.dart';
import '../../features/events/ui/invitation_editor_screen.dart';
import '../../features/events/ui/paywall_placeholder_screen.dart';
import '../../features/events/ui/recipients_screen.dart';
import '../../features/events/ui/rsvp_tracking_screen.dart';
import '../../features/events/ui/send_screen.dart';
import '../../features/home/ui/home_shell.dart';
import '../../features/home/ui/tabs.dart';
import '../../features/inbox/ui/envelope_open_screen.dart';
import '../../features/inbox/ui/inbox_screen.dart';
import '../../features/inbox/ui/invitation_screen.dart';

abstract final class Routes {
  static const splash = '/acilis';
  static const welcome = '/hosgeldin';
  static const phone = '/giris';
  static const code = '/giris/kod';
  static const profileSetup = '/profil-tamamla';
  static const inbox = '/posta-kutusu';
  static const events = '/etkinlikler';
  static const profile = '/profil';
  static const envelope = '/zarf/:id';
  static const invitation = '/davetiye/:id';
  static const newEvent = '/etkinlikler/yeni';
  static const paywall = '/paket/:id';

  static const _signedOutOnly = {welcome, phone, code};

  static String envelopeFor(int id) => '/zarf/$id';
  static String invitationFor(int id) => '/davetiye/$id';
  static String eventFor(int id) => '/etkinlikler/$id';
  static String editorFor(int id) => '/etkinlikler/$id/davetiye';
  static String recipientsFor(int id) => '/etkinlikler/$id/davetliler';
  static String contactsFor(int id) => '/etkinlikler/$id/davetliler/rehber';
  static String sendFor(int id) => '/etkinlikler/$id/gonder';
  static String rsvpFor(int id) => '/etkinlikler/$id/lcv';
  static String paywallFor(int eventId) => '/paket/$eventId';

  static String codeFor(String phone) => Uri(path: code, queryParameters: {'phone': phone}).toString();
}

/// Oturum durumuna göre gidilecek yer; `null` = olduğu yerde kal.
@visibleForTesting
String? redirectFor(SessionState session, String location) {
  return switch (session) {
    SessionLoading() || SessionUnreachable() => location == Routes.splash ? null : Routes.splash,
    SignedOut() => Routes._signedOutOnly.contains(location) ? null : Routes.welcome,
    SignedIn(:final user) when !user.isProfileComplete => location == Routes.profileSetup ? null : Routes.profileSetup,
    SignedIn() =>
      (Routes._signedOutOnly.contains(location) || location == Routes.splash || location == Routes.profileSetup)
          ? Routes.inbox
          : null,
  };
}

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = ValueNotifier<int>(0);
  ref.listen(sessionProvider, (_, _) => refresh.value++);
  ref.onDispose(refresh.dispose);

  final router = GoRouter(
    initialLocation: Routes.splash,
    refreshListenable: refresh,
    redirect: (context, state) => redirectFor(ref.read(sessionProvider), state.matchedLocation),
    routes: [
      GoRoute(path: Routes.splash, builder: (_, _) => const SplashScreen()),
      GoRoute(path: Routes.welcome, builder: (_, _) => const WelcomeScreen()),
      GoRoute(path: Routes.phone, builder: (_, _) => const PhoneScreen()),
      GoRoute(
        path: Routes.code,
        builder: (_, state) => CodeScreen(phone: state.uri.queryParameters['phone'] ?? ''),
      ),
      GoRoute(path: Routes.profileSetup, builder: (_, _) => const ProfileSetupScreen()),
      // Sekmelerin üstünde tam ekran (M7, M8).
      GoRoute(
        path: Routes.envelope,
        builder: (_, state) => EnvelopeOpenScreen(id: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(
        path: Routes.invitation,
        builder: (_, state) => InvitationScreen(id: int.parse(state.pathParameters['id']!)),
      ),
      // M14 yer tutucu (Adım 8'de gerçek paywall).
      GoRoute(
        path: Routes.paywall,
        builder: (_, state) => PaywallPlaceholderScreen(eventId: int.parse(state.pathParameters['id']!)),
      ),
      StatefulShellRoute.indexedStack(
        builder: (_, _, shell) => HomeShell(shell: shell),
        branches: [
          StatefulShellBranch(
            routes: [GoRoute(path: Routes.inbox, builder: (_, _) => const InboxScreen())],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: Routes.events,
                builder: (_, _) => const EventsScreen(),
                routes: [
                  GoRoute(path: 'yeni', builder: (_, _) => const EventCreateScreen()),
                  GoRoute(
                    path: ':id',
                    builder: (_, state) => EventDetailScreen(id: _id(state)),
                    routes: [
                      GoRoute(
                        path: 'davetiye',
                        builder: (_, state) => InvitationEditorScreen(eventId: _id(state)),
                      ),
                      GoRoute(
                        path: 'davetliler',
                        builder: (_, state) => RecipientsScreen(eventId: _id(state)),
                        routes: [
                          GoRoute(
                            path: 'rehber',
                            builder: (_, state) => ContactPickerScreen(eventId: _id(state)),
                          ),
                        ],
                      ),
                      GoRoute(
                        path: 'gonder',
                        builder: (_, state) => SendScreen(eventId: _id(state)),
                      ),
                      GoRoute(
                        path: 'lcv',
                        builder: (_, state) => RsvpTrackingScreen(eventId: _id(state)),
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: Routes.profile, builder: (_, _) => const ProfileTab())],
          ),
        ],
      ),
    ],
  );
  ref.onDispose(router.dispose);

  return router;
});

int _id(GoRouterState state) => int.parse(state.pathParameters['id']!);
