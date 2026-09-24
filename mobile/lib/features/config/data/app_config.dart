import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';

/// Server-driven configuration: feature flags and public business settings.
class AppConfig {
  const AppConfig({required this.features, required this.settings, required this.updateRequired, required this.serverTime});

  factory AppConfig.fromJson(Map<String, dynamic> json) {
    final update = json['update'] as Map<String, dynamic>? ?? const {};
    return AppConfig(
      features: Map<String, bool>.from(json['features'] as Map? ?? const {}),
      settings: Map<String, dynamic>.from(json['settings'] as Map? ?? const {}),
      updateRequired: update['required'] == true,
      serverTime: json['server_time'] as int?,
    );
  }

  /// Used only until the first successful /config call. Everything off except basics.
  static const fallback = AppConfig(features: {}, settings: {}, updateRequired: false, serverTime: null);

  final Map<String, bool> features;
  final Map<String, dynamic> settings;
  final bool updateRequired;
  final int? serverTime;

  bool feature(String key) => features[key] ?? false;

  List<int> get dailyGoalOptions =>
      (settings['activity.daily_goal_options'] as List?)?.map((e) => (e as num).toInt()).toList() ?? const [5000, 7500, 10000, 12500, 15000];
  int get minDailyGoal => (settings['activity.min_daily_goal'] as num?)?.toInt() ?? 1000;
  int get maxDailyGoal => (settings['activity.max_daily_goal'] as num?)?.toInt() ?? 50000;
  int get glassMl => (settings['health.glass_ml'] as num?)?.toInt() ?? 250;
  int get otpLength => (settings['auth.otp_length'] as num?)?.toInt() ?? 5;
  int get otpResendSeconds => (settings['auth.otp_resend_seconds'] as num?)?.toInt() ?? 60;
  int get deletionGraceDays => (settings['account.deletion_grace_days'] as num?)?.toInt() ?? 14;
}

final appConfigProvider = FutureProvider<AppConfig>((ref) async {
  final api = ref.watch(apiClientProvider);
  final response = await api.get('/config');
  final config = AppConfig.fromJson(response['data'] as Map<String, dynamic>);
  if (config.serverTime != null) await api.syncClock(config.serverTime!);
  return config;
});

/// Synchronous access for widgets; falls back until loaded.
final configProvider = Provider<AppConfig>((ref) => ref.watch(appConfigProvider).value ?? AppConfig.fallback);
