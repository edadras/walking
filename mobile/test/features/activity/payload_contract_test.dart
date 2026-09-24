import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/sensors/step_platform.dart';
import 'package:gamyar/features/activity/domain/active_walk_mapper.dart';
import 'package:gamyar/features/activity/domain/day_splitter.dart';
import 'package:gamyar/features/activity/domain/passive_builder.dart';
import 'package:timezone/data/latest_10y.dart' as tz_data;
import 'package:timezone/timezone.dart' as tz;

/// Contract with the backend: the exact JSON the app sends is pinned in
/// /contracts and submitted by backend/tests/Feature/Api/V1/PayloadContractTest.php.
/// Regenerate with UPDATE_CONTRACTS=1 flutter test test/features/activity/payload_contract_test.dart
void main() {
  setUpAll(tz_data.initializeTimeZones);

  Map<String, Object?> batch() {
    final clock = DayClock(tz.getLocation('Asia/Tehran'));
    DateTime teh(int h, int m) => tz.TZDateTime(clock.location, 2026, 9, 24, h, m).toUtc();

    final passive = PassiveSessionBuilder(clock).build([
      StepReading(time: teh(8, 0), counter: 1000, bootCount: 2),
      StepReading(time: teh(8, 20), counter: 2240, bootCount: 2),
      StepReading(time: teh(8, 35), counter: 2600, bootCount: 2),
    ], [ActivityTransition(time: teh(7, 58), type: 'walking', enter: true)]).sessions;

    final start = teh(18, 0);
    final active = draftsFromActiveWalk({
      'started_at_ms': start.millisecondsSinceEpoch,
      'ended_at_ms': start.add(const Duration(minutes: 3)).millisecondsSinceEpoch,
      'buckets': [
        for (var i = 0; i < 3; i++)
          {
            'started_at_ms': start.add(Duration(minutes: i)).millisecondsSinceEpoch,
            'duration_s': 60,
            'steps': 112 + i,
            'detector_steps': 110 + i,
            'accel_std': 2.35,
            'accel_peak_hz': 1.84,
            'speed_mps': 1.4,
            'gps_accuracy_m': 8,
          },
      ],
      'gps': {'points': 36, 'distance_m': 262.5, 'avg_accuracy_m': 7.9, 'max_speed_mps': 1.9, 'jumps': 0, 'mock_detected': false},
      'mock_location': false,
    }, clock);

    var sequence = 0;
    final drafts = [...passive, ...active];
    return {
      'sessions': [
        for (final (i, d) in drafts.indexed) {...d.toJson(sequence: ++sequence), 'client_session_id': '00000000-0000-4000-8000-00000000000${i + 1}'},
      ],
    };
  }

  test('session batch payload matches the backend contract', () {
    final file = File('../contracts/walking_session_batch.json');
    final encoded = const JsonEncoder.withIndent('  ').convert(batch());
    if (Platform.environment['UPDATE_CONTRACTS'] == '1' || !file.existsSync()) {
      file.writeAsStringSync('$encoded\n');
    }
    expect(encoded, file.readAsStringSync().trimRight());
  });
}
