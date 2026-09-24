import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import 'me.dart';

class UserDevice {
  const UserDevice({required this.id, required this.model, required this.appVersion, required this.lastSeenAt, required this.isCurrent});

  factory UserDevice.fromJson(Map<String, dynamic> j) => UserDevice(
        id: j['id'] as String,
        model: j['model'] as String?,
        appVersion: j['app_version'] as String?,
        lastSeenAt: DateTime.tryParse(j['last_seen_at'] as String? ?? ''),
        isCurrent: j['is_current'] == true,
      );

  final String id;
  final String? model;
  final String? appVersion;
  final DateTime? lastSeenAt;
  final bool isCurrent;
}

class ProfileRepository {
  ProfileRepository(this._api);

  final ApiClient _api;

  Me _me(Map<String, dynamic> r) => Me.fromJson(r['data'] as Map<String, dynamic>);

  Future<Me> updateProfile(Map<String, Object?> fields) async => _me(await _api.patch('/me', data: fields));

  /// Multipart and unsigned: the server re-encodes the image, so the bytes aren't evidence of anything.
  Future<Me> uploadAvatar(String filePath) async => _me(await _api.post(
        '/me/avatar',
        data: FormData.fromMap({'avatar': await MultipartFile.fromFile(filePath, filename: 'avatar.jpg')}),
        options: Options(contentType: 'multipart/form-data', sendTimeout: const Duration(seconds: 60)),
      ));

  Future<Me> removeAvatar() async => _me(await _api.delete('/me/avatar'));

  Future<Me> updateSettings(Map<String, Object?> fields) async => _me(await _api.patch('/me/settings', data: fields));

  Future<Map<String, bool>> notificationPreferences() async =>
      Map<String, bool>.from((await _api.get('/me/notification-preferences'))['data'] as Map);

  Future<Map<String, bool>> updateNotificationPreferences(Map<String, bool> prefs) async =>
      Map<String, bool>.from((await _api.patch('/me/notification-preferences', data: {'preferences': prefs}))['data'] as Map);

  Future<List<UserDevice>> devices() async =>
      ((await _api.get('/auth/devices'))['data'] as List).map((e) => UserDevice.fromJson(e as Map<String, dynamic>)).toList();

  Future<void> revokeDevice(String id) => _api.delete('/auth/devices/$id', options: Req.signed());

  Future<DateTime?> requestDeletion() async {
    final r = await _api.post('/me/deletion-request', options: Req.signed());
    return DateTime.tryParse((r['data'] as Map)['scheduled_for'] as String? ?? '');
  }

  Future<void> cancelDeletion() => _api.delete('/me/deletion-request');

  Future<({String title, String body})> page(String slug) async {
    final d = (await _api.get('/pages/$slug', options: Req.anonymous()))['data'] as Map<String, dynamic>;
    return (title: d['title'] as String, body: d['body'] as String);
  }

  Future<List<({String category, String question, String answer})>> faqs() async {
    final list = (await _api.get('/faqs', options: Req.anonymous()))['data'] as List;
    return list.map((e) {
      final m = e as Map<String, dynamic>;
      return (category: m['category'] as String, question: m['question'] as String, answer: m['answer'] as String);
    }).toList();
  }
}

final profileRepositoryProvider = Provider<ProfileRepository>((ref) => ProfileRepository(ref.watch(apiClientProvider)));

final devicesProvider = FutureProvider.autoDispose<List<UserDevice>>((ref) => ref.watch(profileRepositoryProvider).devices());

final notificationPrefsProvider = FutureProvider.autoDispose<Map<String, bool>>(
  (ref) => ref.watch(profileRepositoryProvider).notificationPreferences(),
);

final cmsPageProvider = FutureProvider.autoDispose.family<({String title, String body}), String>(
  (ref, slug) => ref.watch(profileRepositoryProvider).page(slug),
);

final faqsProvider = FutureProvider.autoDispose((ref) => ref.watch(profileRepositoryProvider).faqs());
