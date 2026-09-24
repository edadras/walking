import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/api_client.dart';
import '../providers.dart';

/// First-party product analytics: a small in-memory queue flushed in batches to
/// `/analytics/events`. Names must be on the server's whitelist; properties are
/// short scalars and never personal data (no phone, name, coordinates, codes).
/// Losing a few events on a crash is acceptable — nothing here drives rewards.
class Analytics {
  Analytics(this._api, {this.batchSize = 20, Duration flushEvery = const Duration(seconds: 30), DateTime Function()? clock})
      : _clock = clock ?? DateTime.now {
    _timer = Timer.periodic(flushEvery, (_) => unawaited(flush()));
    _lifecycle = AppLifecycleListener(onPause: () => unawaited(flush()), onDetach: () => unawaited(flush()));
  }

  final ApiClient _api;
  final int batchSize;
  final DateTime Function() _clock;
  late final Timer _timer;
  late final AppLifecycleListener _lifecycle;
  final _queue = <Map<String, Object?>>[];
  bool _enabled = false;
  bool _flushing = false;

  static const maxQueue = 200;

  @visibleForTesting
  int get pending => _queue.length;

  /// The endpoint needs a signed-in user: events before sign-in (onboarding,
  /// permission prompts) wait in the queue; signing out drops what's left.
  set enabled(bool value) {
    if (_enabled && !value) _queue.clear();
    _enabled = value;
    if (value) unawaited(flush());
  }

  void track(String name, [Map<String, Object?> properties = const {}]) {
    if (_queue.length >= maxQueue) _queue.removeAt(0);
    _queue.add({
      'name': name,
      'occurred_at': _clock().toUtc().toIso8601String(),
      if (properties.isNotEmpty) 'properties': properties,
    });
    if (_enabled && _queue.length >= batchSize) unawaited(flush());
  }

  Future<void> flush() async {
    if (_flushing || _queue.isEmpty || !_enabled) return;
    _flushing = true;
    final batch = _queue.take(50).toList();
    try {
      await _api.post('/analytics/events', data: {'events': batch});
      _queue.removeRange(0, batch.length);
    } catch (_) {
      // Kept for the next flush; the queue cap bounds memory while offline.
    } finally {
      _flushing = false;
    }
  }

  void dispose() {
    _timer.cancel();
    _lifecycle.dispose();
  }
}

final analyticsProvider = Provider<Analytics>((ref) {
  final analytics = Analytics(ref.watch(apiClientProvider));
  ref.onDispose(analytics.dispose);
  return analytics;
});

/// For code without a [Ref] (static helpers, plain widgets). Silently no-op
/// outside a ProviderScope so helpers stay usable in isolation.
void trackFrom(BuildContext context, String name, [Map<String, Object?> properties = const {}]) {
  try {
    ProviderScope.containerOf(context, listen: false).read(analyticsProvider).track(name, properties);
  } catch (_) {}
}
