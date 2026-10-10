import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import 'event.dart';
import 'invitation.dart';

/// Etkinlik, şablon, davetiye, davetli ve gönderim uçları (spec §6.3, §6.4).
class EventsRepository {
  EventsRepository(this._dio);

  final Dio _dio;

  Future<List<AppEvent>> list() async {
    final r = await ApiException.guard(() => _dio.get('/events'));
    return (r.data as List).map((e) => AppEvent.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<AppEvent> get(int id) async {
    final r = await ApiException.guard(() => _dio.get('/events/$id'));
    return AppEvent.fromJson(r.data as Map<String, dynamic>);
  }

  Future<AppEvent> create(EventDraft draft) async {
    final r = await ApiException.guard(() => _dio.post('/events', data: draft.toJson()));
    return AppEvent.fromJson(r.data as Map<String, dynamic>);
  }

  Future<AppEvent> setHeadcountSharing(int id, bool share) async {
    final r = await ApiException.guard(() => _dio.patch('/events/$id/headcount-sharing', data: {'share': share}));
    return AppEvent.fromJson(r.data as Map<String, dynamic>);
  }

  Future<List<Template>> templates(EventType type) async {
    final r = await ApiException.guard(() => _dio.get('/templates', queryParameters: {'event_type': type.value}));
    return (r.data as List).map((e) => Template.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// Davetiye yoksa `null`.
  Future<InvitationDraft?> invitation(int eventId) async {
    try {
      final r = await ApiException.guard(() => _dio.get('/events/$eventId/invitation'));
      return InvitationDraft.fromJson(r.data as Map<String, dynamic>);
    } on ApiException catch (e) {
      if (e.statusCode == 404) return null;
      rethrow;
    }
  }

  /// @throws ApiException `402 pack_required` paketsiz premium seçimde.
  Future<InvitationDraft> saveInvitation(int eventId, InvitationDraft draft) async {
    final r = await ApiException.guard(() => _dio.put('/events/$eventId/invitation', data: draft.toJson()));
    return InvitationDraft.fromJson(r.data as Map<String, dynamic>);
  }

  Future<List<Recipient>> recipients(int eventId) async {
    final r = await ApiException.guard(() => _dio.get('/events/$eventId/invitation/recipients'));
    return (r.data as List).map((e) => Recipient.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// @throws ApiException `402 pack_required` sınır aşılırsa (hiçbiri eklenmez).
  Future<({int added, int skipped})> addRecipients(int eventId, List<({String phone, String? name})> people) async {
    final r = await ApiException.guard(
      () => _dio.post(
        '/events/$eventId/invitation/recipients',
        data: {
          'recipients': [
            for (final p in people) {'phone': p.phone, if (p.name != null && p.name!.isNotEmpty) 'name': p.name},
          ],
        },
      ),
    );
    final data = r.data as Map<String, dynamic>;
    return (added: (data['added'] as List).length, skipped: data['skipped_count'] as int);
  }

  Future<void> deleteRecipient(int eventId, int recipientId) =>
      ApiException.guard(() => _dio.delete('/events/$eventId/invitation/recipients/$recipientId'));

  /// Rehberdeki hash'lerden uygulamada kayıtlı olanlar. Sunucu tek seferde en fazla 2.000 kabul eder.
  Future<Set<String>> matchContacts(List<String> hashes) async {
    final registered = <String>{};
    for (var i = 0; i < hashes.length; i += contactMatchBatch) {
      final batch = hashes.sublist(i, (i + contactMatchBatch).clamp(0, hashes.length));
      final r = await ApiException.guard(() => _dio.post('/contacts/match', data: {'hashes': batch}));
      registered.addAll(((r.data as Map)['registered'] as List).cast<String>());
    }
    return registered;
  }

  static const contactMatchBatch = 2000;

  Future<SendResult> send(int eventId) async {
    final r = await ApiException.guard(() => _dio.post('/events/$eventId/invitation/send'));
    return SendResult.fromJson(r.data as Map<String, dynamic>);
  }

  Future<RsvpStats> stats(int eventId) async {
    final r = await ApiException.guard(() => _dio.get('/events/$eventId/invitation/stats'));
    return RsvpStats.fromJson(r.data as Map<String, dynamic>);
  }
}

final eventsRepositoryProvider = Provider<EventsRepository>((ref) => EventsRepository(ref.watch(dioProvider)));

final eventsProvider = FutureProvider<List<AppEvent>>((ref) => ref.watch(eventsRepositoryProvider).list());

final eventProvider = FutureProvider.family<AppEvent, int>((ref, id) => ref.watch(eventsRepositoryProvider).get(id));

final invitationProvider = FutureProvider.family<InvitationDraft?, int>(
  (ref, eventId) => ref.watch(eventsRepositoryProvider).invitation(eventId),
);

final templatesProvider = FutureProvider.family<List<Template>, EventType>(
  (ref, type) => ref.watch(eventsRepositoryProvider).templates(type),
);

final recipientsProvider = FutureProvider.family<List<Recipient>, int>(
  (ref, eventId) => ref.watch(eventsRepositoryProvider).recipients(eventId),
);

final statsProvider = FutureProvider.family<RsvpStats, int>(
  (ref, eventId) => ref.watch(eventsRepositoryProvider).stats(eventId),
);

/// Etkinlikle ilgili her şeyi tazeler (davetiye kaydı, davetli ekleme, gönderim sonrası).
void refreshEvent(WidgetRef ref, int eventId) {
  ref.invalidate(eventsProvider);
  ref.invalidate(eventProvider(eventId));
  ref.invalidate(invitationProvider(eventId));
  ref.invalidate(recipientsProvider(eventId));
  ref.invalidate(statsProvider(eventId));
}
