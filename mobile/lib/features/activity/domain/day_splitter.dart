import 'package:timezone/timezone.dart' as tz;

import 'session_draft.dart';

/// Calendar logic in the user's timezone (the same one the server uses).
class DayClock {
  DayClock(this.location);

  final tz.Location location;

  String dateOf(DateTime instant) {
    final l = tz.TZDateTime.from(instant, location);
    return '${l.year.toString().padLeft(4, '0')}-${l.month.toString().padLeft(2, '0')}-${l.day.toString().padLeft(2, '0')}';
  }

  /// First local midnight strictly after [instant], as a UTC instant.
  DateTime nextMidnight(DateTime instant) {
    final l = tz.TZDateTime.from(instant, location);
    return tz.TZDateTime(location, l.year, l.month, l.day + 1).toUtc();
  }
}

/// Splits [bucket] at local midnight and at [maxSeconds], distributing steps
/// proportionally to time. The sum of steps is always preserved exactly.
List<DraftBucket> splitBucket(DraftBucket bucket, DayClock clock, {required int maxSeconds}) {
  final cuts = <DateTime>[bucket.start];
  var cursor = bucket.start;
  final end = bucket.end;
  while (cursor.isBefore(end)) {
    final byLength = cursor.add(Duration(seconds: maxSeconds));
    final byDay = clock.nextMidnight(cursor);
    var next = byLength.isBefore(byDay) ? byLength : byDay;
    if (next.isAfter(end)) next = end;
    cuts.add(next);
    cursor = next;
  }
  if (cuts.length == 2) return [bucket];

  final total = bucket.durationS;
  final pieces = <DraftBucket>[];
  final shares = <int>[];
  final remainders = <double>[];
  for (var i = 0; i < cuts.length - 1; i++) {
    final seconds = cuts[i + 1].difference(cuts[i]).inSeconds;
    final exact = bucket.steps * seconds / total;
    shares.add(exact.floor());
    remainders.add(exact - exact.floor());
    pieces.add(bucket.copyWith(start: cuts[i], durationS: seconds, steps: 0, detectorSteps: null));
  }
  // Largest remainder method keeps the total exact.
  var missing = bucket.steps - shares.fold(0, (a, b) => a + b);
  final order = List.generate(shares.length, (i) => i)..sort((a, b) => remainders[b].compareTo(remainders[a]));
  for (final i in order) {
    if (missing <= 0) break;
    shares[i]++;
    missing--;
  }
  return [
    for (var i = 0; i < pieces.length; i++)
      if (pieces[i].durationS > 0) pieces[i].copyWith(steps: shares[i]),
  ];
}
