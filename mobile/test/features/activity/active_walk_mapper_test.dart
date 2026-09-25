import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/features/activity/domain/active_walk_mapper.dart';
import 'package:gamyar/features/activity/domain/day_splitter.dart';
import 'package:timezone/data/latest_10y.dart' as tz_data;
import 'package:timezone/timezone.dart' as tz;

void main() {
  setUpAll(tz_data.initializeTimeZones);

  test('splits a walk that crosses local midnight into one draft per day', () {
    final clock = DayClock(tz.getLocation('Asia/Tehran'));
    final start = tz.TZDateTime(clock.location, 2026, 9, 24, 23, 58, 30).toUtc();
    final buckets = [
      for (var i = 0; i < 3; i++)
        {'started_at_ms': start.add(Duration(minutes: i)).millisecondsSinceEpoch, 'duration_s': 60, 'steps': 120, 'detector_steps': 118, 'accel_std': 2.1, 'accel_peak_hz': 1.9},
    ];
    final drafts = draftsFromActiveWalk({
      'started_at_ms': start.millisecondsSinceEpoch,
      'ended_at_ms': start.add(const Duration(minutes: 3)).millisecondsSinceEpoch,
      'buckets': buckets,
      'gps': {'points': 30, 'distance_m': 250.0, 'jumps': 0, 'mock_detected': false},
      'mock_location': false,
    }, clock);

    expect(drafts.map((d) => d.localDate), ['2026-09-24', '2026-09-25']);
    expect(drafts.fold<int>(0, (s, d) => s + d.steps), 360);
    expect(drafts.first.end.isAtSameMomentAs(tz.TZDateTime(clock.location, 2026, 9, 25)), isTrue);
    expect(drafts.first.gps, isNotNull);
    expect(drafts.last.gps, isNull);
    expect(drafts.every((d) => d.kind == 'active'), isTrue);
    expect(drafts.first.buckets.first.accelPeakHz, 1.9);
  });

  test('keeps the bicycle label and speed of each minute for the server', () {
    final clock = DayClock(tz.getLocation('Asia/Tehran'));
    final start = tz.TZDateTime(clock.location, 2026, 9, 24, 10).toUtc();
    final drafts = draftsFromActiveWalk({
      'started_at_ms': start.millisecondsSinceEpoch,
      'ended_at_ms': start.add(const Duration(minutes: 2)).millisecondsSinceEpoch,
      'buckets': [
        {'started_at_ms': start.millisecondsSinceEpoch, 'duration_s': 60, 'steps': 2, 'accel_std': 1.4, 'speed_mps': 5.2, 'gps_accuracy_m': 6, 'activity_type': 'bicycle'},
        {'started_at_ms': start.add(const Duration(minutes: 1)).millisecondsSinceEpoch, 'duration_s': 60, 'steps': 1, 'speed_mps': 5.0, 'activity_type': null},
      ],
      'gps': {'points': 24, 'distance_m': 610.0, 'max_speed_mps': 6.1, 'jumps': 0, 'mock_detected': false},
      'mock_location': false,
      'route': [[35.7, 51.4, 1], [35.701, 51.401, 2]],
    }, clock);

    final json = drafts.single.buckets.map((b) => b.toJson()).toList();
    expect(json.first['activity_type'], 'bicycle');
    expect(json.first['speed_mps'], 5.2);
    expect(json.last.containsKey('activity_type'), isFalse);
  });
}
