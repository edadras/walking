import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/sensors/step_platform.dart';
import 'package:gamyar/features/activity/domain/day_splitter.dart';
import 'package:gamyar/features/activity/domain/passive_builder.dart';
import 'package:timezone/data/latest_10y.dart' as tz_data;
import 'package:timezone/timezone.dart' as tz;

void main() {
  late DayClock clock;
  late PassiveSessionBuilder builder;

  setUpAll(tz_data.initializeTimeZones);
  setUp(() {
    clock = DayClock(tz.getLocation('Asia/Tehran'));
    builder = PassiveSessionBuilder(clock);
  });

  /// Tehran local wall time → UTC instant.
  DateTime teh(int day, int hour, [int minute = 0]) => tz.TZDateTime(clock.location, 2026, 9, day, hour, minute).toUtc();

  StepReading r(DateTime t, int c, {int boot = 1, String mark = ''}) => StepReading(time: t, counter: c, bootCount: boot, mark: mark);

  int total(PassiveBuildResult res) => res.sessions.fold(0, (s, x) => s + x.steps);

  test('needs at least two readings', () {
    expect(builder.build([r(teh(24, 10), 100)], const []).sessions, isEmpty);
  });

  test('turns consecutive deltas into one contiguous session', () {
    final res = builder.build([r(teh(24, 10), 1000), r(teh(24, 10, 15), 1600), r(teh(24, 10, 30), 2100)], const []);

    expect(res.sessions, hasLength(1));
    final s = res.sessions.single;
    expect(s.kind, 'passive');
    expect(s.steps, 1100);
    expect(s.buckets.map((b) => b.steps), [600, 500]);
    expect(s.localDate, '2026-09-24');
    expect(res.readingsBefore!.isAtSameMomentAs(teh(24, 10, 30)), isTrue);
  });

  test('windows with no steps are dropped and break contiguity', () {
    final res = builder.build([r(teh(24, 9), 500), r(teh(24, 9, 30), 800), r(teh(24, 10), 800), r(teh(24, 10, 30), 1000)], const []);

    expect(res.sessions, hasLength(2));
    expect(total(res), 500);
  });

  test('a reboot restarts the counter: the new value is steps since boot', () {
    final res = builder.build([r(teh(24, 10), 5000, boot: 3), r(teh(24, 10, 30), 250, boot: 4)], const []);

    expect(total(res), 250);
  });

  test('a lower counter without a boot id is also treated as a reboot', () {
    final res = builder.build([r(teh(24, 10), 5000, boot: -1), r(teh(24, 10, 30), 300, boot: -1)], const []);

    expect(total(res), 300);
  });

  test('steps inside a user-started walk are not counted twice', () {
    final res = builder.build([
      r(teh(24, 9), 100),
      r(teh(24, 9, 30), 400),
      r(teh(24, 9, 40), 450, mark: StepReading.activeStart),
      r(teh(24, 10, 40), 6450, mark: StepReading.activeEnd),
      r(teh(24, 11), 6700),
    ], const []);

    // 300 + 50 before the walk, 250 after; the 6000 walk steps belong to the active session.
    expect(total(res), 600);
  });

  test('windows are cut at local midnight and steps are preserved exactly', () {
    final res = builder.build([r(teh(24, 23, 30), 0), r(teh(25, 0, 30), 1001)], const []);

    expect(res.sessions.map((s) => s.localDate), ['2026-09-24', '2026-09-25']);
    // Equal halves; the odd step goes to one of them (largest-remainder tie).
    expect(res.sessions.map((s) => s.steps).toList()..sort(), [500, 501]);
    expect(res.sessions.first.end.isAtSameMomentAs(teh(25, 0)), isTrue);
  });

  test('long gaps become ≤60 min buckets in ≤120 min sessions', () {
    final res = builder.build([r(teh(24, 8), 0), r(teh(24, 13), 5000)], const []);

    expect(total(res), 5000);
    for (final s in res.sessions) {
      expect(s.end.difference(s.start).inMinutes, lessThanOrEqualTo(120));
      for (final b in s.buckets) {
        expect(b.durationS, lessThanOrEqualTo(3600));
      }
    }
    expect(res.sessions, hasLength(3));
  });

  test('labels windows with the dominant recognised activity', () {
    final res = builder.build(
      [r(teh(24, 10), 0), r(teh(24, 10, 30), 900)],
      [
        ActivityTransition(time: teh(24, 9, 55), type: 'walking', enter: true),
        ActivityTransition(time: teh(24, 10, 5), type: 'vehicle', enter: true),
      ],
    );

    expect(res.sessions.single.buckets.single.activityType, 'vehicle');
  });

  test('payload is what the API expects', () {
    final s = builder.build([r(teh(24, 10), 0), r(teh(24, 10, 20), 700)], const []).sessions.single;
    final json = s.toJson(sequence: 7);

    expect(json['sequence'], 7);
    expect(json['raw_steps'], 700);
    expect(json['started_at'], '2026-09-24T06:30:00.000Z');
    expect((json['buckets']! as List).single, {'started_at': '2026-09-24T06:30:00.000Z', 'duration_s': 1200, 'steps': 700});
  });
}
