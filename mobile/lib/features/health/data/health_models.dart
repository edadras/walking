int _i(Object? v) => (v as num?)?.toInt() ?? 0;
double _d(Object? v) => (v as num?)?.toDouble() ?? 0;

class HealthDay {
  const HealthDay({required this.date, required this.steps, required this.distanceM, required this.caloriesKcal, required this.activeMinutes, required this.goal, required this.goalReached});

  factory HealthDay.fromJson(Map<String, dynamic> j) => HealthDay(
        date: DateTime.parse(j['date'] as String),
        steps: _i(j['steps']),
        distanceM: _i(j['distance_m']),
        caloriesKcal: _d(j['calories_kcal']),
        activeMinutes: _i(j['active_minutes']),
        goal: _i(j['goal']),
        goalReached: j['goal_reached'] == true,
      );

  final DateTime date;
  final int steps;
  final int distanceM;
  final double caloriesKcal;
  final int activeMinutes;
  final int goal;
  final bool goalReached;
}

class HealthSummaryData {
  const HealthSummaryData({
    required this.days,
    required this.totalSteps,
    required this.totalDistanceM,
    required this.totalCalories,
    required this.totalActiveMinutes,
    required this.goalDays,
    required this.avgDaily,
    required this.avgWeekly,
    required this.avgMonthly,
    required this.streakCurrent,
    required this.streakLongest,
    required this.bestDay,
    required this.bestWeek,
    required this.bestSession,
  });

  factory HealthSummaryData.fromJson(Map<String, dynamic> j) {
    final t = j['totals'] as Map<String, dynamic>;
    final a = j['averages'] as Map<String, dynamic>;
    final s = j['streak'] as Map<String, dynamic>;
    final r = (j['records'] as Map?)?.cast<String, dynamic>() ?? const {};
    int rec(String k) => _i((r[k] as Map?)?['value']);
    return HealthSummaryData(
      days: (j['days'] as List).map((e) => HealthDay.fromJson(e as Map<String, dynamic>)).toList(),
      totalSteps: _i(t['steps']),
      totalDistanceM: _i(t['distance_m']),
      totalCalories: _d(t['calories_kcal']),
      totalActiveMinutes: _i(t['active_minutes']),
      goalDays: _i(t['goal_days']),
      avgDaily: _i(a['daily_steps']),
      avgWeekly: _i(a['weekly_steps']),
      avgMonthly: _i(a['monthly_steps']),
      streakCurrent: _i(s['current']),
      streakLongest: _i(s['longest']),
      bestDay: rec('best_day_steps'),
      bestWeek: rec('best_week_steps'),
      bestSession: rec('best_session_steps'),
    );
  }

  final List<HealthDay> days;
  final int totalSteps;
  final int totalDistanceM;
  final double totalCalories;
  final int totalActiveMinutes;
  final int goalDays;
  final int avgDaily;
  final int avgWeekly;
  final int avgMonthly;
  final int streakCurrent;
  final int streakLongest;
  final int bestDay;
  final int bestWeek;
  final int bestSession;
}

class WeeklyReport {
  const WeeklyReport({required this.weekStart, required this.daysElapsed, required this.steps, required this.distanceM, required this.calories, required this.points, required this.activeMinutes, required this.goalDays, required this.previousSteps, required this.changePercent});

  factory WeeklyReport.fromJson(Map<String, dynamic> j) => WeeklyReport(
        weekStart: DateTime.parse(j['week_start'] as String),
        daysElapsed: _i(j['days_elapsed']),
        steps: _i(j['steps']),
        distanceM: _i(j['distance_m']),
        calories: _d(j['calories_kcal']),
        points: _i(j['points']),
        activeMinutes: _i(j['active_minutes']),
        goalDays: _i(j['goal_days']),
        previousSteps: _i(j['previous_steps']),
        changePercent: (j['change_percent'] as num?)?.toInt(),
      );

  final DateTime weekStart;
  final int daysElapsed;
  final int steps;
  final int distanceM;
  final double calories;
  final int points;
  final int activeMinutes;
  final int goalDays;
  final int previousSteps;
  final int? changePercent;
}

class WaterDay {
  const WaterDay({required this.goalMl, required this.glassMl, required this.totalMl, required this.goalGlasses, required this.suggestedGoalMl, required this.reminderEnabled, required this.reminderIntervalMin, required this.logs});

  factory WaterDay.fromJson(Map<String, dynamic> j) => WaterDay(
        goalMl: _i(j['goal_ml']),
        glassMl: _i(j['glass_ml']),
        totalMl: _i(j['total_ml']),
        goalGlasses: _i(j['goal_glasses']),
        suggestedGoalMl: _i(j['suggested_goal_ml']),
        reminderEnabled: j['reminder_enabled'] == true,
        reminderIntervalMin: _i(j['reminder_interval_min']),
        logs: (j['logs'] as List).map((e) => e as Map<String, dynamic>).map((e) => (id: _i(e['id']), amountMl: _i(e['amount_ml']), at: DateTime.parse(e['logged_at'] as String))).toList(),
      );

  final int goalMl;
  final int glassMl;
  final int totalMl;
  final int goalGlasses;
  final int suggestedGoalMl;
  final bool reminderEnabled;
  final int reminderIntervalMin;
  final List<({int id, int amountMl, DateTime at})> logs;

  double get glasses => glassMl == 0 ? 0 : totalMl / glassMl;
}
