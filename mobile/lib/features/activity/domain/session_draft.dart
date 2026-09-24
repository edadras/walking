import 'package:uuid/uuid.dart';

/// A bucket of a session: one minute of an active walk, or one background window.
class DraftBucket {
  const DraftBucket({
    required this.start,
    required this.durationS,
    required this.steps,
    this.detectorSteps,
    this.accelStd,
    this.accelPeakHz,
    this.activityType,
    this.speedMps,
    this.gpsAccuracyM,
  });

  final DateTime start;
  final int durationS;
  final int steps;
  final int? detectorSteps;
  final double? accelStd;
  final double? accelPeakHz;
  final String? activityType;
  final double? speedMps;
  final int? gpsAccuracyM;

  DateTime get end => start.add(Duration(seconds: durationS));

  DraftBucket copyWith({DateTime? start, int? durationS, int? steps, int? detectorSteps}) => DraftBucket(
        start: start ?? this.start,
        durationS: durationS ?? this.durationS,
        steps: steps ?? this.steps,
        detectorSteps: detectorSteps ?? this.detectorSteps,
        accelStd: accelStd,
        accelPeakHz: accelPeakHz,
        activityType: activityType,
        speedMps: speedMps,
        gpsAccuracyM: gpsAccuracyM,
      );

  Map<String, Object?> toJson() => {
        'started_at': start.toUtc().toIso8601String(),
        'duration_s': durationS,
        'steps': steps,
        'detector_steps': ?detectorSteps,
        'accel_std': ?accelStd,
        'accel_peak_hz': ?accelPeakHz,
        'activity_type': ?activityType,
        'speed_mps': ?speedMps,
        'gps_accuracy_m': ?gpsAccuracyM,
      };
}

/// A session ready for the offline queue. It carries only observations;
/// distance, calories and verification are computed by the server.
class SessionDraft {
  SessionDraft({
    String? clientSessionId,
    required this.kind,
    required this.localDate,
    required this.buckets,
    this.gps,
    this.motion,
  }) : clientSessionId = clientSessionId ?? const Uuid().v4();

  final String clientSessionId;
  final String kind; // passive | active
  final String localDate; // yyyy-MM-dd in the user's timezone
  final List<DraftBucket> buckets;
  final Map<String, Object?>? gps;
  final Map<String, Object?>? motion;

  DateTime get start => buckets.first.start;
  DateTime get end => buckets.last.end;
  int get steps => buckets.fold(0, (s, b) => s + b.steps);

  Map<String, Object?> toJson({required int sequence}) => {
        'client_session_id': clientSessionId,
        'sequence': sequence,
        'kind': kind,
        'source': 'step_counter',
        'started_at': start.toUtc().toIso8601String(),
        'ended_at': end.toUtc().toIso8601String(),
        'raw_steps': steps,
        'buckets': buckets.map((b) => b.toJson()).toList(),
        'gps': ?gps,
        'motion': ?motion,
      };
}
