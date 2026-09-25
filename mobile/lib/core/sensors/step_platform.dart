import 'package:flutter/services.dart';

/// Raw cumulative step-counter reading written by the native background worker.
class StepReading {
  const StepReading({required this.time, required this.counter, required this.bootCount, this.mark = ''});

  factory StepReading.fromMap(Map<dynamic, dynamic> m) => StepReading(
        time: DateTime.fromMillisecondsSinceEpoch((m['t'] as num).toInt(), isUtc: true),
        counter: (m['c'] as num).toInt(),
        bootCount: (m['b'] as num?)?.toInt() ?? -1,
        mark: m['m'] as String? ?? '',
      );

  static const activeStart = 'active_start';
  static const activeEnd = 'active_end';

  final DateTime time;
  final int counter;
  final int bootCount;
  final String mark;
}

class ActivityTransition {
  const ActivityTransition({required this.time, required this.type, required this.enter});

  factory ActivityTransition.fromMap(Map<dynamic, dynamic> m) => ActivityTransition(
        time: DateTime.fromMillisecondsSinceEpoch((m['t'] as num).toInt(), isUtc: true),
        type: m['type'] as String,
        enter: m['enter'] == true,
      );

  final DateTime time;
  final String type;
  final bool enter;
}

class StepCapabilities {
  const StepCapabilities({required this.stepCounter, required this.stepDetector, required this.activityPermission});

  final bool stepCounter;
  final bool stepDetector;
  final bool activityPermission;
}

/// How the user is moving right now, as the phone sees it (the server decides for points).
enum MoveMode { walking, running, cycling, vehicle, still }

class LiveWalk {
  const LiveWalk({
    required this.steps,
    required this.elapsed,
    required this.distanceM,
    required this.gps,
    this.mode = MoveMode.still,
    this.cyclingDistanceM = 0,
    this.speedKmh = 0,
  });

  factory LiveWalk.fromMap(Map<dynamic, dynamic> m) => LiveWalk(
        steps: (m['steps'] as num).toInt(),
        elapsed: Duration(seconds: (m['elapsed_s'] as num).toInt()),
        distanceM: (m['distance_m'] as num?)?.toDouble() ?? 0,
        gps: m['gps'] == true,
        mode: MoveMode.values.asNameMap()[m['mode']] ?? MoveMode.still,
        cyclingDistanceM: (m['cycling_distance_m'] as num?)?.toDouble() ?? 0,
        speedKmh: (m['speed_kmh'] as num?)?.toDouble() ?? 0,
      );

  final int steps;
  final Duration elapsed;
  final double distanceM;
  final bool gps;
  final MoveMode mode;
  final double cyclingDistanceM;
  final double speedKmh;
}

/// Access to on-device step data. The sensor implementation talks to
/// android/.../steps; a Health Connect implementation can be added behind the
/// same interface (docs/phase-0/07-tracking-offline.md §7.2).
abstract class StepPlatform {
  Future<StepCapabilities> capabilities();

  /// Schedules background reads + activity transitions. Returns false without permission.
  Future<bool> startPassive();

  Future<void> readNow();

  Future<(List<StepReading>, List<ActivityTransition>)> pending();

  Future<void> acknowledge({DateTime? readingsBefore, DateTime? transitionsBefore});

  Future<void> startActiveWalk({required bool gps});

  /// Ends the walk and returns the raw native payload (null if none was running).
  Future<Map<String, dynamic>?> stopActiveWalk();

  Future<LiveWalk?> activeWalk();

  Stream<LiveWalk> liveWalk();
}

class MethodChannelStepPlatform implements StepPlatform {
  static const _methods = MethodChannel('ir.gamyar/steps');
  static const _events = EventChannel('ir.gamyar/steps/live');

  @override
  Future<StepCapabilities> capabilities() async {
    final m = await _methods.invokeMapMethod<String, Object?>('capabilities') ?? const {};
    return StepCapabilities(
      stepCounter: m['step_counter'] == true,
      stepDetector: m['step_detector'] == true,
      activityPermission: m['activity_permission'] == true,
    );
  }

  @override
  Future<bool> startPassive() async => await _methods.invokeMethod<bool>('startPassive') ?? false;

  @override
  Future<void> readNow() => _methods.invokeMethod<void>('readNow');

  @override
  Future<(List<StepReading>, List<ActivityTransition>)> pending() async {
    final m = await _methods.invokeMapMethod<String, Object?>('readings') ?? const {};
    final readings = (m['readings'] as List? ?? const []).map((e) => StepReading.fromMap(e as Map)).toList();
    final transitions = (m['transitions'] as List? ?? const []).map((e) => ActivityTransition.fromMap(e as Map)).toList();
    return (readings, transitions);
  }

  @override
  Future<void> acknowledge({DateTime? readingsBefore, DateTime? transitionsBefore}) => _methods.invokeMethod<void>('ack', {
        'readings_before': ?readingsBefore?.millisecondsSinceEpoch,
        'transitions_before': ?transitionsBefore?.millisecondsSinceEpoch,
      });

  @override
  Future<void> startActiveWalk({required bool gps}) => _methods.invokeMethod<void>('startActive', {'gps': gps});

  @override
  Future<Map<String, dynamic>?> stopActiveWalk() async {
    final m = await _methods.invokeMethod<Map<dynamic, dynamic>>('stopActive');
    return m == null ? null : _deepCast(m);
  }

  @override
  Future<LiveWalk?> activeWalk() async {
    final m = await _methods.invokeMethod<Map<dynamic, dynamic>>('activeState');
    return m == null ? null : LiveWalk.fromMap(m);
  }

  @override
  Stream<LiveWalk> liveWalk() => _events.receiveBroadcastStream().map((e) => LiveWalk.fromMap(e as Map));

  static Map<String, dynamic> _deepCast(Map<dynamic, dynamic> m) => m.map((k, v) => MapEntry(
        k.toString(),
        switch (v) {
          final Map<dynamic, dynamic> map => _deepCast(map),
          final List<dynamic> list => list.map((e) => e is Map ? _deepCast(e) : e).toList(),
          _ => v,
        },
      ));
}
