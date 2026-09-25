import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../format/numbers.dart';

/// Pushes the home screen's numbers to the Android home-screen widgets and the
/// lock-screen "today" notification. Native code adds steps counted since (pending).
class HomeWidgetBridge {
  static const _channel = MethodChannel('ir.gamyar.app/widget');

  Future<void> update({required int steps, required int goal, required int streak, int distanceM = 0, int kcal = 0, DateTime? now}) async {
    final d = now ?? DateTime.now();
    try {
      await _channel.invokeMethod<bool>('update', {
        'steps': steps,
        'goal': goal,
        'streak': streak > 0 ? '🔥 ${Fa.digits(streak)} روز' : '',
        'percent': goal <= 0 ? 0 : (steps * 100 ~/ goal).clamp(0, 100),
        'distance_m': distanceM,
        'kcal': kcal,
        // The device's local day the numbers belong to; after midnight the widget asks for a sync.
        'date': '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}',
      });
    } on MissingPluginException {
      // Tests / non-Android.
    } on PlatformException {
      // A widget failure must never break the home screen.
    }
  }

  /// The latest weather report for the walking-weather widget (and the temperature on the step widgets).
  /// Only what the widgets show is stored; the place itself never is.
  Future<void> updateWeather(Map<String, Object?> summary) async {
    try {
      await _channel.invokeMethod<bool>('weather', {'json': jsonEncode(summary)});
    } on MissingPluginException {
      // Tests / non-Android.
    } on PlatformException {
      // Ignore.
    }
  }

  /// Today's steps as a quiet ongoing notification on the lock screen (default on).
  Future<bool> lockScreenEnabled() async {
    try {
      return await _channel.invokeMethod<bool>('lockscreenEnabled') ?? true;
    } on MissingPluginException {
      return false;
    } on PlatformException {
      return false;
    }
  }

  Future<void> setLockScreen(bool enabled) async {
    try {
      await _channel.invokeMethod<bool>('lockscreen', {'enabled': enabled});
    } on MissingPluginException {
      // Tests / non-Android.
    } on PlatformException {
      // Ignore.
    }
  }

  /// Signing out must not leave the previous account's numbers on the launcher.
  Future<void> clear() async {
    try {
      await _channel.invokeMethod<bool>('clear');
    } on MissingPluginException {
      // Tests / non-Android.
    } on PlatformException {
      // Ignore.
    }
  }
}

final homeWidgetProvider = Provider<HomeWidgetBridge>((ref) => HomeWidgetBridge());

final lockScreenStepsProvider = FutureProvider.autoDispose<bool>((ref) => ref.watch(homeWidgetProvider).lockScreenEnabled());
