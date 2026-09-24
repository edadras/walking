import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../config/data/app_config.dart';
import '../data/ads_repository.dart';

const rewardedPlacement = 'rewarded_default';

/// One ad per placement per screen visit. Nothing is requested when ads are off.
final placementAdProvider = FutureProvider.autoDispose.family<AdCreative?, String>((ref, key) async {
  if (!ref.watch(configProvider).feature('ads')) return null;
  try {
    return await ref.watch(adsRepositoryProvider).placement(key);
  } catch (_) {
    return null; // an ad must never break a screen
  }
});

final rewardedStatusProvider = FutureProvider.autoDispose<RewardedStatus?>((ref) async {
  final config = ref.watch(configProvider);
  if (!config.feature('ads') || !config.feature('rewarded_ads')) return null;
  try {
    return await ref.watch(adsRepositoryProvider).rewardedStatus(rewardedPlacement);
  } catch (_) {
    return null;
  }
});

/// Batches impression/click reports (best effort; the server de-duplicates).
class AdEventQueue {
  AdEventQueue(this._repo, {this.delay = const Duration(seconds: 3)});

  final AdsRepository _repo;
  final Duration delay;
  final _pending = <({String token, String type})>[];
  Timer? _timer;

  void add(String token, String type) {
    _pending.add((token: token, type: type));
    _timer ??= Timer(delay, flush);
  }

  Future<void> flush() async {
    _timer?.cancel();
    _timer = null;
    if (_pending.isEmpty) return;
    final batch = List.of(_pending);
    _pending.clear();
    try {
      await _repo.events(batch);
    } catch (_) {
      // Dropped: ad stats are not worth retries that drain battery.
    }
  }

  void dispose() => _timer?.cancel();
}

final adEventQueueProvider = Provider<AdEventQueue>((ref) {
  final q = AdEventQueue(ref.watch(adsRepositoryProvider));
  ref.onDispose(q.dispose);
  return q;
});
