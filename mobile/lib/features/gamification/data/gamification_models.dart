int _i(Object? v) => (v as num?)?.toInt() ?? 0;

class LevelProgress {
  const LevelProgress({required this.level, required this.title, required this.xp, required this.currentMin, required this.nextMin});

  factory LevelProgress.fromJson(Map<String, dynamic> j) => LevelProgress(
        level: _i(j['level']),
        title: j['title'] as String? ?? '',
        xp: _i(j['xp']),
        currentMin: _i(j['current_min']),
        nextMin: (j['next_min'] as num?)?.toInt(),
      );

  final int level;
  final String title;
  final int xp;
  final int currentMin;
  final int? nextMin;

  double get fraction => nextMin == null ? 1 : ((xp - currentMin) / (nextMin! - currentMin)).clamp(0, 1).toDouble();
}

class StreakWeek {
  const StreakWeek({required this.current, required this.days, this.longest = 0, this.freezes = StreakFreezes.none});

  factory StreakWeek.fromJson(Map<String, dynamic> j) => StreakWeek(
        current: _i(j['current']),
        longest: _i(j['longest']),
        days: ((j['week'] as List?) ?? const [])
            .map((e) => e as Map<String, dynamic>)
            .map((e) => (date: DateTime.parse(e['date'] as String), reached: e['reached'] == true, frozen: e['frozen'] == true, future: e['future'] == true))
            .toList(),
        freezes: j['freezes'] == null ? StreakFreezes.none : StreakFreezes.fromJson(j['freezes'] as Map<String, dynamic>),
      );

  final int current;
  final int longest;
  final List<({DateTime date, bool reached, bool frozen, bool future})> days;
  final StreakFreezes freezes;
}

/// Streak freezes: bought with points, spent automatically on one missed day.
class StreakFreezes {
  const StreakFreezes({required this.owned, required this.max, required this.price, required this.canBuy});

  factory StreakFreezes.fromJson(Map<String, dynamic> j) =>
      StreakFreezes(owned: _i(j['owned']), max: _i(j['max']), price: _i(j['price']), canBuy: j['can_buy'] == true);

  static const none = StreakFreezes(owned: 0, max: 0, price: 0, canBuy: false);

  final int owned;
  final int max;
  final int price;
  final bool canBuy;
}

class AchievementItem {
  const AchievementItem({required this.key, required this.name, required this.description, required this.icon, required this.threshold, required this.progress, required this.unlockedAt, required this.xpReward, required this.pointReward});

  factory AchievementItem.fromJson(Map<String, dynamic> j) => AchievementItem(
        key: j['key'] as String,
        name: j['name'] as String,
        description: j['description'] as String,
        icon: j['icon'] as String? ?? 'medal',
        threshold: _i(j['threshold']),
        progress: _i(j['progress']),
        unlockedAt: DateTime.tryParse(j['unlocked_at'] as String? ?? ''),
        xpReward: _i(j['xp_reward']),
        pointReward: _i(j['point_reward']),
      );

  final String key;
  final String name;
  final String description;
  final String icon;
  final int threshold;
  final int progress;
  final DateTime? unlockedAt;
  final int xpReward;
  final int pointReward;

  bool get unlocked => unlockedAt != null;
  double get fraction => threshold == 0 ? 0 : (progress / threshold).clamp(0, 1).toDouble();
}

class LeaderboardEntry {
  const LeaderboardEntry({required this.rank, required this.name, required this.avatarUrl, required this.level, required this.steps, required this.isMe});

  factory LeaderboardEntry.fromJson(Map<String, dynamic> j) => LeaderboardEntry(
        rank: _i(j['rank']),
        name: j['name'] as String,
        avatarUrl: j['avatar_url'] as String?,
        level: _i(j['level']),
        steps: _i(j['steps'] ?? j['score']),
        isMe: j['is_me'] == true,
      );

  final int rank;
  final String name;
  final String? avatarUrl;
  final int level;
  final int steps;
  final bool isMe;
}

class Leaderboard {
  const Leaderboard({required this.entries, required this.me, required this.visible});

  factory Leaderboard.fromJson(Map<String, dynamic> j) {
    final me = j['me'] as Map<String, dynamic>?;
    return Leaderboard(
      entries: (j['entries'] as List).map((e) => LeaderboardEntry.fromJson(e as Map<String, dynamic>)).toList(),
      me: me == null ? null : LeaderboardEntry.fromJson({...me, 'is_me': true}),
      visible: me == null || me['visible'] != false,
    );
  }

  final List<LeaderboardEntry> entries;
  final LeaderboardEntry? me;
  final bool visible;
}

class ReferralSummary {
  const ReferralSummary({required this.code, this.shareUrl, required this.qualifySteps, required this.referrerPoints, required this.refereePoints, required this.invited, required this.rewarded, required this.pointsEarned});

  factory ReferralSummary.fromJson(Map<String, dynamic> j) => ReferralSummary(
        code: j['code'] as String,
        shareUrl: j['share_url'] as String?,
        qualifySteps: _i(j['qualify_steps']),
        referrerPoints: _i(j['referrer_points']),
        refereePoints: _i(j['referee_points']),
        invited: _i(j['invited']),
        rewarded: _i(j['rewarded']),
        pointsEarned: _i(j['points_earned']),
      );

  final String code;

  /// https invite link (opens the app via App Links, or the store page).
  final String? shareUrl;
  final int qualifySteps;
  final int referrerPoints;
  final int refereePoints;
  final int invited;
  final int rewarded;
  final int pointsEarned;
}
