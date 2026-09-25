/// The signed-in user, as returned by GET /me.
class Me {
  const Me({
    required this.id,
    required this.phoneMasked,
    required this.publicName,
    required this.displayName,
    required this.avatarUrl,
    required this.status,
    required this.level,
    required this.xp,
    required this.referralCode,
    required this.timezone,
    required this.joinedAt,
    required this.deletionRequestedAt,
    required this.birthYear,
    required this.gender,
    required this.heightCm,
    required this.weightKg,
    required this.dailyStepGoal,
    required this.waterGoalMl,
    required this.waterReminderEnabled,
    required this.leaderboardVisible,
    required this.profileCompleted,
    this.shareRoute = false,
    this.routeColor,
  });

  factory Me.fromJson(Map<String, dynamic> j) {
    final profile = (j['profile'] as Map?)?.cast<String, dynamic>() ?? const {};
    final settings = (j['settings'] as Map?)?.cast<String, dynamic>() ?? const {};
    return Me(
      id: j['id'] as String,
      phoneMasked: j['phone_masked'] as String? ?? '',
      publicName: j['public_name'] as String? ?? '',
      displayName: j['display_name'] as String?,
      avatarUrl: j['avatar_url'] as String?,
      status: j['status'] as String? ?? 'active',
      level: (j['level'] as num?)?.toInt() ?? 1,
      xp: (j['xp'] as num?)?.toInt() ?? 0,
      referralCode: j['referral_code'] as String? ?? '',
      timezone: j['timezone'] as String? ?? 'Asia/Tehran',
      joinedAt: DateTime.tryParse(j['joined_at'] as String? ?? ''),
      deletionRequestedAt: DateTime.tryParse(j['deletion_requested_at'] as String? ?? ''),
      birthYear: (profile['birth_year'] as num?)?.toInt(),
      gender: profile['gender'] as String?,
      heightCm: (profile['height_cm'] as num?)?.toInt(),
      weightKg: (profile['weight_kg'] as num?)?.toDouble(),
      dailyStepGoal: (settings['daily_step_goal'] as num?)?.toInt() ?? 7500,
      waterGoalMl: (settings['water_goal_ml'] as num?)?.toInt() ?? 2000,
      waterReminderEnabled: settings['water_reminder_enabled'] == true,
      leaderboardVisible: settings['leaderboard_visible'] != false,
      shareRoute: settings['share_route'] == true,
      routeColor: settings['route_color'] as String?,
      profileCompleted: j['profile_completed'] == true,
    );
  }

  final String id;
  final String phoneMasked;
  final String publicName;
  final String? displayName;
  final String? avatarUrl;
  final String status;
  final int level;
  final int xp;
  final String referralCode;
  final String timezone;
  final DateTime? joinedAt;
  final DateTime? deletionRequestedAt;
  final int? birthYear;
  final String? gender;
  final int? heightCm;
  final double? weightKg;
  final int dailyStepGoal;
  final int waterGoalMl;
  final bool waterReminderEnabled;
  final bool leaderboardVisible;
  final bool profileCompleted;

  /// Opt-in: finished walks appear (anonymously, 24 h) on the public map in this colour.
  final bool shareRoute;
  final String? routeColor;
}
