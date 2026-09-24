import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import 'activity_models.dart';

/// Outcome for one queued session in a batch sync.
class SyncItemResult {
  const SyncItemResult({required this.clientSessionId, required this.status, this.errorCode});

  final String clientSessionId;
  final String status; // accepted | duplicate | rejected
  final String? errorCode;
}

class ActivityRepository {
  ActivityRepository(this._api);

  final ApiClient _api;

  Future<HomeData> home() async => HomeData.fromJson((await _api.get('/home'))['data'] as Map<String, dynamic>);

  Future<DayActivity> day([String? date]) async =>
      DayActivity.fromJson((await _api.get('/activity/day', query: {'date': ?date}))['data'] as Map<String, dynamic>);

  Future<List<DailySummary>> daily(String from, String to) async =>
      ((await _api.get('/activity/daily', query: {'from': from, 'to': to}))['data'] as List)
          .map((e) => DailySummary.fromJson(e as Map<String, dynamic>))
          .toList();

  Future<WalkSession> session(String id) async =>
      WalkSession.fromJson((await _api.get('/walking-sessions/$id'))['data'] as Map<String, dynamic>);

  Future<List<SyncItemResult>> submitBatch(List<Map<String, dynamic>> sessions) async {
    final response = await _api.post('/walking-sessions/batch', data: {'sessions': sessions}, options: Req.signed());
    return (response['data'] as List).map((e) {
      final m = e as Map<String, dynamic>;
      return SyncItemResult(
        clientSessionId: m['client_session_id'] as String,
        status: m['status'] as String,
        errorCode: (m['error'] as Map?)?['code'] as String?,
      );
    }).toList();
  }
}

final activityRepositoryProvider = Provider<ActivityRepository>((ref) => ActivityRepository(ref.watch(apiClientProvider)));
