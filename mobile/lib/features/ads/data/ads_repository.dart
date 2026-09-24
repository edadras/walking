import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';

class AdCreative {
  const AdCreative({required this.id, required this.format, required this.title, required this.minViewSeconds, this.body, this.imageUrl, this.ctaLabel, this.actionUrl, this.advertiser, this.token});

  factory AdCreative.fromJson(Map<String, dynamic> j) => AdCreative(
        id: j['id'] as String,
        format: j['format'] as String,
        title: j['title'] as String,
        body: j['body'] as String?,
        imageUrl: j['image_url'] as String?,
        ctaLabel: j['cta_label'] as String?,
        actionUrl: j['action_url'] as String?,
        minViewSeconds: (j['min_view_seconds'] as num?)?.toInt() ?? 15,
        advertiser: j['advertiser'] as String?,
        token: j['token'] as String?,
      );

  final String id;
  final String format; // banner | native | rewarded
  final String title;
  final String? body;
  final String? imageUrl;
  final String? ctaLabel;
  final String? actionUrl;
  final int minViewSeconds;
  final String? advertiser;

  /// Serve receipt; events are only accepted with it.
  final String? token;
}

class RewardedStatus {
  const RewardedStatus({required this.enabled, required this.remainingToday, required this.dailyCap});

  factory RewardedStatus.fromJson(Map<String, dynamic> j) =>
      RewardedStatus(enabled: j['enabled'] == true, remainingToday: (j['remaining_today'] as num?)?.toInt() ?? 0, dailyCap: (j['daily_cap'] as num?)?.toInt() ?? 0);

  final bool enabled;
  final int remainingToday;
  final int dailyCap;

  bool get available => enabled && remainingToday > 0;
}

class RewardedView {
  const RewardedView({required this.id, required this.status, required this.ad, required this.rewardPoints, required this.pointsAwarded, required this.startedAt, this.rejectionReason});

  factory RewardedView.fromJson(Map<String, dynamic> j) => RewardedView(
        id: j['id'] as String,
        status: j['status'] as String,
        ad: AdCreative.fromJson(j['ad'] as Map<String, dynamic>),
        rewardPoints: (j['reward_points'] as num?)?.toInt() ?? 0,
        pointsAwarded: (j['points_awarded'] as num?)?.toInt() ?? 0,
        rejectionReason: j['rejection_reason'] as String?,
        startedAt: DateTime.parse(j['started_at'] as String),
      );

  final String id;
  final String status; // started | rewarded | rejected | expired
  final AdCreative ad;
  final int rewardPoints;
  final int pointsAwarded;
  final String? rejectionReason;
  final DateTime startedAt;
}

class AdsRepository {
  AdsRepository(this._api);

  final ApiClient _api;

  Future<AdCreative?> placement(String key) async {
    final d = (await _api.get('/ads/placements/$key'))['data'];
    return d == null ? null : AdCreative.fromJson(d as Map<String, dynamic>);
  }

  Future<void> events(List<({String token, String type})> events) =>
      _api.post('/ads/events', data: {'events': [for (final e in events) {'token': e.token, 'type': e.type}]});

  Future<RewardedStatus> rewardedStatus(String placement) async =>
      RewardedStatus.fromJson((await _api.get('/ads/rewarded', query: {'placement': placement}))['data'] as Map<String, dynamic>);

  Future<RewardedView> startRewarded(String placement) async =>
      RewardedView.fromJson((await _api.post('/ads/rewarded/start', data: {'placement': placement}, options: Req.signed()))['data'] as Map<String, dynamic>);

  Future<RewardedView> completeRewarded(String viewId) async =>
      RewardedView.fromJson((await _api.post('/ads/rewarded/$viewId/complete', options: Req.signed()))['data'] as Map<String, dynamic>);
}

final adsRepositoryProvider = Provider<AdsRepository>((ref) => AdsRepository(ref.watch(apiClientProvider)));
