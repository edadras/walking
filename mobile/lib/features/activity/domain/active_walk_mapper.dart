import 'day_splitter.dart';
import 'session_draft.dart';

/// Converts the native active-walk payload into session drafts, split at local
/// midnight so each draft belongs to exactly one day (server requirement).
List<SessionDraft> draftsFromActiveWalk(Map<String, dynamic> payload, DayClock clock) {
  DateTime ms(Object? v) => DateTime.fromMillisecondsSinceEpoch((v as num).toInt(), isUtc: true);
  double? d(Object? v) => (v as num?)?.toDouble();
  int? i(Object? v) => (v as num?)?.toInt();

  final raw = (payload['buckets'] as List? ?? const [])
      .cast<Map<String, dynamic>>()
      .map((b) => DraftBucket(
            start: ms(b['started_at_ms']),
            durationS: i(b['duration_s'])!,
            steps: i(b['steps'])!,
            detectorSteps: i(b['detector_steps']),
            accelStd: d(b['accel_std']),
            accelPeakHz: d(b['accel_peak_hz']),
            speedMps: d(b['speed_mps']),
            gpsAccuracyM: i(b['gps_accuracy_m']),
            activityType: b['activity_type'] as String?,
          ))
      .expand((b) => splitBucket(b, clock, maxSeconds: 60))
      .where((b) => b.durationS > 0)
      .toList();
  if (raw.isEmpty) return const [];

  final gps = (payload['gps'] as Map?)?.cast<String, Object?>();
  final motion = <String, Object?>{'mock_location': payload['mock_location'] == true};

  final byDay = <String, List<DraftBucket>>{};
  for (final b in raw) {
    byDay.putIfAbsent(clock.dateOf(b.start), () => []).add(b);
  }
  // GPS totals can't be split meaningfully; they stay on the day the walk started.
  final firstDay = clock.dateOf(raw.first.start);
  return [
    for (final entry in byDay.entries)
      SessionDraft(kind: 'active', localDate: entry.key, buckets: entry.value, gps: entry.key == firstDay ? gps : null, motion: motion),
  ];
}
