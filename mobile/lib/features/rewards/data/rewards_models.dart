import '../../wallet/data/wallet_models.dart';

int _i(Object? v) => (v as num?)?.toInt() ?? 0;
double _d(Object? v) => (v as num?)?.toDouble() ?? 0;

class RewardCenter {
  const RewardCenter({
    required this.pointsToday,
    required this.rialToday,
    required this.verifiedSteps,
    required this.remainingRewardableSteps,
    required this.remainingPoints,
    required this.goal,
    required this.goalReached,
    required this.wallet,
    required this.stepsPerUnit,
    required this.pointsPerUnit,
    required this.dailyCap,
    required this.maxRewardedSteps,
    required this.goalBonus,
    required this.streakBonuses,
    required this.multiplierNow,
    required this.upcomingMultipliers,
    required this.recent,
  });

  factory RewardCenter.fromJson(Map<String, dynamic> j) {
    final t = j['today'] as Map<String, dynamic>;
    final e = j['earning'] as Map<String, dynamic>;
    return RewardCenter(
      pointsToday: _i(t['points']),
      rialToday: _i(t['rial_value']),
      verifiedSteps: _i(t['verified_steps']),
      remainingRewardableSteps: _i(t['remaining_rewardable_steps']),
      remainingPoints: _i(t['remaining_points']),
      goal: _i(t['goal']),
      goalReached: t['goal_reached'] == true,
      wallet: WalletBalance.fromJson(j['wallet'] as Map<String, dynamic>),
      stepsPerUnit: _i(e['steps_per_unit']),
      pointsPerUnit: _i(e['points_per_unit']),
      dailyCap: _i(e['daily_cap']),
      maxRewardedSteps: _i(e['max_rewarded_steps']),
      goalBonus: _i(e['goal_bonus']),
      streakBonuses: ((e['streak_bonuses'] as Map?) ?? const {}).map((k, v) => MapEntry(int.parse('$k'), _i(v))),
      multiplierNow: _d(e['multiplier_now']),
      upcomingMultipliers: ((e['upcoming_multipliers'] as List?) ?? const [])
          .map((m) => m as Map<String, dynamic>)
          .map((m) => (date: DateTime.parse(m['date'] as String), multiplier: _d(m['multiplier']), names: (m['names'] as List).cast<String>()))
          .toList(),
      recent: ((j['recent'] as List?) ?? const []).map((r) => RewardItem.fromJson(r as Map<String, dynamic>)).toList(),
    );
  }

  final int pointsToday;
  final int rialToday;
  final int verifiedSteps;
  final int remainingRewardableSteps;
  final int remainingPoints;
  final int goal;
  final bool goalReached;
  final WalletBalance wallet;
  final int stepsPerUnit;
  final int pointsPerUnit;
  final int dailyCap;
  final int maxRewardedSteps;
  final int goalBonus;
  final Map<int, int> streakBonuses;
  final double multiplierNow;
  final List<({DateTime date, double multiplier, List<String> names})> upcomingMultipliers;
  final List<RewardItem> recent;
}

class RewardItem {
  const RewardItem({required this.id, required this.kind, required this.points, required this.status, required this.createdAt, required this.multiplier});

  factory RewardItem.fromJson(Map<String, dynamic> j) => RewardItem(
        id: j['id'] as String,
        kind: j['kind'] as String,
        points: _i(j['points']),
        status: j['status'] as String,
        multiplier: _d(j['multiplier']),
        createdAt: DateTime.parse(j['created_at'] as String),
      );

  final String id;
  final String kind;
  final int points;
  final String status;
  final double multiplier;
  final DateTime createdAt;
}
