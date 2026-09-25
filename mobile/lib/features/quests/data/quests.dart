import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';

int _i(Object? v) => (v as num?)?.toInt() ?? 0;

/// A daily or weekly mission; progress is computed by the server from verified data.
class Quest {
  const Quest({
    required this.key,
    required this.title,
    required this.period,
    required this.unit,
    required this.target,
    required this.progress,
    required this.rewardPoints,
    required this.rewardXp,
    required this.claimed,
    required this.claimable,
    this.description,
  });

  factory Quest.fromJson(Map<String, dynamic> j) => Quest(
        key: j['key'] as String,
        title: j['title'] as String,
        description: j['description'] as String?,
        period: j['period'] as String,
        unit: j['unit'] as String? ?? '',
        target: _i(j['target']),
        progress: _i(j['progress']),
        rewardPoints: _i(j['reward_points']),
        rewardXp: _i(j['reward_xp']),
        claimed: j['claimed'] == true,
        claimable: j['claimable'] == true,
      );

  final String key;
  final String title;
  final String? description;
  final String period; // daily | weekly
  final String unit;
  final int target;
  final int progress;
  final int rewardPoints;
  final int rewardXp;
  final bool claimed;
  final bool claimable;

  double get fraction => target == 0 ? 0 : (progress / target).clamp(0, 1).toDouble();
}

List<Quest> _parse(Map<String, dynamic> r) => (r['data'] as List).map((e) => Quest.fromJson(e as Map<String, dynamic>)).toList();

final questsProvider = FutureProvider.autoDispose<List<Quest>>((ref) async => _parse(await ref.watch(apiClientProvider).get('/quests')));

/// Server re-checks progress and pays at most once per period.
Future<List<Quest>> claimQuest(ApiClient api, String key) async => _parse(await api.post('/quests/$key/claim', options: Req.signed()));
