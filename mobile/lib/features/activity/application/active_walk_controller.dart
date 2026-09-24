import 'dart:async';

import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/sensors/step_platform.dart';
import 'activity_providers.dart';
import 'tracking_service.dart';

sealed class ActiveWalkState {
  const ActiveWalkState();
}

class WalkIdle extends ActiveWalkState {
  const WalkIdle();
}

class WalkRunning extends ActiveWalkState {
  const WalkRunning(this.live);

  final LiveWalk live;
}

class WalkSaving extends ActiveWalkState {
  const WalkSaving(this.live);

  final LiveWalk live;
}

class WalkFinished extends ActiveWalkState {
  const WalkFinished({required this.steps, required this.duration, required this.synced});

  final int steps;
  final Duration duration;

  /// False when the walk is safely queued but couldn't be uploaded yet (offline).
  final bool synced;
}

class WalkFailed extends ActiveWalkState {
  const WalkFailed(this.reason);

  final String reason; // no_sensor | permission | error
}

/// A walk the user starts and stops explicitly (foreground service on Android).
class ActiveWalkController extends Notifier<ActiveWalkState> {
  StreamSubscription<LiveWalk>? _live;

  StepPlatform get _platform => ref.read(stepPlatformProvider);

  @override
  ActiveWalkState build() {
    ref.onDispose(() => _live?.cancel());
    // Re-attach if the app was reopened while a walk is still recording.
    unawaited(_platform.activeWalk().then((live) {
      if (live != null) _attach(live);
    }).catchError((_) {}));
    return const WalkIdle();
  }

  Future<void> start({required bool gps}) async {
    try {
      await _platform.startActiveWalk(gps: gps);
      _attach(LiveWalk(steps: 0, elapsed: Duration.zero, distanceM: 0, gps: gps));
    } on PlatformException catch (e) {
      state = WalkFailed(e.code == 'no_sensor' || e.code == 'permission' ? e.code : 'error');
    }
  }

  Future<void> stop() async {
    final current = state;
    if (current is! WalkRunning) return;
    state = WalkSaving(current.live);
    await _live?.cancel();
    _live = null;

    final payload = await _platform.stopActiveWalk();
    if (payload == null) {
      state = const WalkIdle();
      return;
    }
    final tracking = ref.read(trackingServiceProvider);
    final drafts = await tracking.queueActiveWalk(payload);
    final report = await tracking.flush();
    refreshActivityViews(ref);

    state = WalkFinished(
      steps: drafts.fold(0, (s, d) => s + d.steps),
      duration: Duration(milliseconds: ((payload['ended_at_ms'] as num) - (payload['started_at_ms'] as num)).toInt()),
      synced: report.remaining == 0,
    );
  }

  void reset() => state = const WalkIdle();

  void _attach(LiveWalk initial) {
    state = WalkRunning(initial);
    _live?.cancel();
    _live = _platform.liveWalk().listen((live) {
      if (state is WalkRunning) state = WalkRunning(live);
    });
  }
}

final activeWalkProvider = NotifierProvider<ActiveWalkController, ActiveWalkState>(ActiveWalkController.new);
