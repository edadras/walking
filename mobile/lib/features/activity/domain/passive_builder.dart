import '../../../core/sensors/step_platform.dart';
import 'day_splitter.dart';
import 'session_draft.dart';

class PassiveBuildResult {
  const PassiveBuildResult({required this.sessions, this.readingsBefore, this.transitionsBefore});

  final List<SessionDraft> sessions;

  /// Acknowledge readings older than this (the newest reading stays as the baseline).
  final DateTime? readingsBefore;
  final DateTime? transitionsBefore;
}

/// Turns cumulative step-counter readings into passive sessions.
///
///  - delta between consecutive readings = steps in that window
///  - a lower counter or a changed boot count means the phone rebooted: the new
///    value is the count since boot
///  - intervals between active_start and active_end belong to a user-started
///    walk, which is submitted separately, so they are skipped (no double count)
///  - windows are cut at local midnight and at 60 minutes; sessions group
///    contiguous windows of the same day up to [maxSessionMinutes]
class PassiveSessionBuilder {
  PassiveSessionBuilder(this.clock, {this.maxBucketMinutes = 60, this.maxSessionMinutes = 120});

  final DayClock clock;
  final int maxBucketMinutes;
  final int maxSessionMinutes;

  PassiveBuildResult build(List<StepReading> readings, List<ActivityTransition> transitions) {
    if (readings.length < 2) return const PassiveBuildResult(sessions: []);
    final sorted = [...readings]..sort((a, b) => a.time.compareTo(b.time));
    final sortedTransitions = [...transitions]..sort((a, b) => a.time.compareTo(b.time));

    final buckets = <DraftBucket>[];
    for (var i = 1; i < sorted.length; i++) {
      final a = sorted[i - 1];
      final b = sorted[i];
      if (a.mark == StepReading.activeStart) continue;
      final seconds = b.time.difference(a.time).inSeconds;
      if (seconds <= 0) continue;

      final rebooted = b.bootCount != a.bootCount || b.counter < a.counter;
      final steps = rebooted ? b.counter : b.counter - a.counter;
      if (steps <= 0) continue;

      final window = DraftBucket(start: a.time, durationS: seconds, steps: steps);
      for (final piece in splitBucket(window, clock, maxSeconds: maxBucketMinutes * 60)) {
        if (piece.steps > 0) buckets.add(_withActivity(piece, sortedTransitions));
      }
    }

    final sessions = <SessionDraft>[];
    var current = <DraftBucket>[];
    void flush() {
      if (current.isNotEmpty) {
        sessions.add(SessionDraft(kind: 'passive', localDate: clock.dateOf(current.first.start), buckets: current));
        current = <DraftBucket>[];
      }
    }

    for (final bucket in buckets) {
      if (current.isNotEmpty) {
        final contiguous = current.last.end.isAtSameMomentAs(bucket.start);
        final sameDay = clock.dateOf(current.first.start) == clock.dateOf(bucket.start);
        final fits = bucket.end.difference(current.first.start).inMinutes <= maxSessionMinutes;
        if (!(contiguous && sameDay && fits)) flush();
      }
      current.add(bucket);
    }
    flush();

    final last = sorted.last.time;
    return PassiveBuildResult(
      sessions: sessions,
      readingsBefore: last,
      // Keep a few hours of transitions: they describe the window after the baseline too.
      transitionsBefore: last.subtract(const Duration(hours: 6)),
    );
  }

  /// Labels a window with the activity that was in effect for most of it
  /// (from the last ENTER transitions), so vehicle time can be discounted server-side.
  DraftBucket _withActivity(DraftBucket bucket, List<ActivityTransition> transitions) {
    if (transitions.isEmpty) return bucket;
    final seconds = <String, int>{};
    String? state;
    var cursor = bucket.start;
    for (final t in transitions) {
      if (!t.enter) continue;
      if (!t.time.isAfter(bucket.start)) {
        state = t.type;
        continue;
      }
      if (!t.time.isBefore(bucket.end)) break;
      if (state != null) seconds[state] = (seconds[state] ?? 0) + t.time.difference(cursor).inSeconds;
      state = t.type;
      cursor = t.time;
    }
    if (state != null) seconds[state] = (seconds[state] ?? 0) + bucket.end.difference(cursor).inSeconds;
    if (seconds.isEmpty) return bucket;

    final dominant = seconds.entries.reduce((a, b) => a.value >= b.value ? a : b).key;
    return DraftBucket(start: bucket.start, durationS: bucket.durationS, steps: bucket.steps, activityType: dominant);
  }
}
