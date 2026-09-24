int _i(Object? v) => (v as num?)?.toInt() ?? 0;
double _d(Object? v) => (v as num?)?.toDouble() ?? 0;

class DailySummary {
  const DailySummary({
    required this.date,
    required this.steps,
    required this.verifiedSteps,
    required this.goal,
    required this.goalReached,
    required this.distanceM,
    required this.caloriesKcal,
    required this.activeMinutes,
    required this.points,
  });

  factory DailySummary.fromJson(Map<String, dynamic> j) => DailySummary(
        date: DateTime.parse(j['date'] as String),
        steps: _i(j['steps']),
        verifiedSteps: _i(j['verified_steps']),
        goal: _i(j['goal']),
        goalReached: j['goal_reached'] == true,
        distanceM: _i(j['distance_m']),
        caloriesKcal: _d(j['calories_kcal']),
        activeMinutes: _i(j['active_minutes']),
        points: _i(j['points']),
      );

  final DateTime date;
  final int steps;
  final int verifiedSteps;
  final int goal;
  final bool goalReached;
  final int distanceM;
  final double caloriesKcal;
  final int activeMinutes;
  final int points;
}

class HomeToday {
  const HomeToday({
    required this.date,
    required this.steps,
    required this.verifiedSteps,
    required this.pendingSteps,
    required this.goal,
    required this.distanceM,
    required this.caloriesKcal,
    required this.activeMinutes,
    required this.goalReached,
    required this.lastSyncedAt,
  });

  factory HomeToday.fromJson(Map<String, dynamic> j) => HomeToday(
        date: j['date'] as String,
        steps: _i(j['steps']),
        verifiedSteps: _i(j['verified_steps']),
        pendingSteps: _i(j['pending_steps']),
        goal: _i(j['goal']),
        distanceM: _i(j['distance_m']),
        caloriesKcal: _d(j['calories_kcal']),
        activeMinutes: _i(j['active_minutes']),
        goalReached: j['goal_reached'] == true,
        lastSyncedAt: DateTime.tryParse(j['last_synced_at'] as String? ?? ''),
      );

  final String date;
  final int steps;
  final int verifiedSteps;
  final int pendingSteps;
  final int goal;
  final int distanceM;
  final double caloriesKcal;
  final int activeMinutes;
  final bool goalReached;
  final DateTime? lastSyncedAt;
}

class WeekDay {
  const WeekDay({required this.date, required this.steps, required this.goal});

  final DateTime date;
  final int steps;
  final int goal;
}

class HomeData {
  const HomeData({required this.today, required this.week});

  factory HomeData.fromJson(Map<String, dynamic> j) => HomeData(
        today: HomeToday.fromJson(j['today'] as Map<String, dynamic>),
        week: (j['week'] as List)
            .map((e) => e as Map<String, dynamic>)
            .map((e) => WeekDay(date: DateTime.parse(e['date'] as String), steps: _i(e['steps']), goal: _i(e['goal'])))
            .toList(),
      );

  final HomeToday today;
  final List<WeekDay> week;
}

class WalkSession {
  const WalkSession({
    required this.id,
    required this.kind,
    required this.startedAt,
    required this.endedAt,
    required this.steps,
    required this.verifiedSteps,
    required this.distanceM,
    required this.durationS,
    required this.activeDurationS,
    required this.caloriesKcal,
    required this.activityType,
    required this.status,
    required this.statusLabel,
    required this.samples,
  });

  factory WalkSession.fromJson(Map<String, dynamic> j) => WalkSession(
        id: j['id'] as String,
        kind: j['kind'] as String,
        startedAt: DateTime.parse(j['started_at'] as String),
        endedAt: DateTime.parse(j['ended_at'] as String),
        steps: _i(j['steps']),
        verifiedSteps: (j['verified_steps'] as num?)?.toInt(),
        distanceM: _i(j['distance_m']),
        durationS: _i(j['duration_s']),
        activeDurationS: _i(j['active_duration_s']),
        caloriesKcal: _d(j['calories_kcal']),
        activityType: j['activity_type'] as String? ?? 'unknown',
        status: j['status'] as String,
        statusLabel: j['status_label'] as String? ?? '',
        samples: (j['samples'] as List? ?? const [])
            .map((e) => e as Map<String, dynamic>)
            .map((e) => (start: DateTime.parse(e['started_at'] as String), durationS: _i(e['duration_s']), steps: _i(e['steps'])))
            .toList(),
      );

  final String id;
  final String kind;
  final DateTime startedAt;
  final DateTime endedAt;
  final int steps;
  final int? verifiedSteps;
  final int distanceM;
  final int durationS;
  final int activeDurationS;
  final double caloriesKcal;
  final String activityType;
  final String status;
  final String statusLabel;
  final List<({DateTime start, int durationS, int steps})> samples;

  bool get isActive => kind == 'active';
}

class DayActivity {
  const DayActivity({required this.date, required this.summary, required this.hourly, required this.sessions});

  factory DayActivity.fromJson(Map<String, dynamic> j) => DayActivity(
        date: j['date'] as String,
        summary: j['summary'] == null ? null : DailySummary.fromJson(j['summary'] as Map<String, dynamic>),
        hourly: (j['hourly'] as List).map(_i).toList(),
        sessions: (j['sessions'] as List).map((e) => WalkSession.fromJson(e as Map<String, dynamic>)).toList(),
      );

  final String date;
  final DailySummary? summary;
  final List<int> hourly;
  final List<WalkSession> sessions;
}
