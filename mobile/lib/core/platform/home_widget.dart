import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../format/numbers.dart';

/// Pushes the home screen's numbers to the Android home-screen widget.
class HomeWidgetBridge {
  static const _channel = MethodChannel('ir.gamyar.app/widget');

  Future<void> update({required int steps, required int goal, required int streak}) async {
    try {
      await _channel.invokeMethod<bool>('update', {
        'steps': Fa.number(steps),
        'goal': 'هدف: ${Fa.number(goal)} قدم',
        'streak': streak > 0 ? '🔥 ${Fa.digits(streak)} روز' : '',
        'percent': goal <= 0 ? 0 : (steps * 100 ~/ goal).clamp(0, 100),
      });
    } on MissingPluginException {
      // Tests / non-Android.
    } on PlatformException {
      // A widget failure must never break the home screen.
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
