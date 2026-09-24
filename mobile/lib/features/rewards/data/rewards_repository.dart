import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import 'rewards_models.dart';

final rewardCenterProvider = FutureProvider.autoDispose<RewardCenter>((ref) async {
  final r = await ref.watch(apiClientProvider).get('/rewards');
  return RewardCenter.fromJson(r['data'] as Map<String, dynamic>);
});
