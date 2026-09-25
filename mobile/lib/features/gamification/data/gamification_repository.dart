import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import 'gamification_models.dart';

final progressProvider = FutureProvider.autoDispose<(LevelProgress, StreakWeek)>((ref) async {
  final d = (await ref.watch(apiClientProvider).get('/progress'))['data'] as Map<String, dynamic>;
  return (LevelProgress.fromJson(d['level'] as Map<String, dynamic>), StreakWeek.fromJson(d['streak'] as Map<String, dynamic>));
});

final achievementsProvider = FutureProvider.autoDispose<List<AchievementItem>>((ref) async {
  final list = (await ref.watch(apiClientProvider).get('/achievements'))['data'] as List;
  return list.map((e) => AchievementItem.fromJson(e as Map<String, dynamic>)).toList();
});

final leaderboardProvider = FutureProvider.autoDispose.family<Leaderboard, String>((ref, period) async {
  final d = (await ref.watch(apiClientProvider).get('/leaderboard', query: {'period': period}))['data'] as Map<String, dynamic>;
  return Leaderboard.fromJson(d);
});

final referralProvider = FutureProvider.autoDispose<ReferralSummary>((ref) async {
  final d = (await ref.watch(apiClientProvider).get('/referral'))['data'] as Map<String, dynamic>;
  return ReferralSummary.fromJson(d);
});

/// Buys one streak freeze; the key makes a retried tap charge only once.
Future<StreakWeek> buyStreakFreeze(ApiClient api, String idempotencyKey) async {
  final d = await api.post('/streak/freezes', options: Req.signed(Options(headers: {'Idempotency-Key': idempotencyKey})));
  return StreakWeek.fromJson(d['data'] as Map<String, dynamic>);
}
