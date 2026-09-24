import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:timezone/timezone.dart' as tz;

/// Water reminders are LOCAL notifications: they work offline, need no server
/// and stop the moment the user turns them off. Scheduled as daily repeating
/// times between [startHour] and [endHour] (never at night), inexact to spare
/// the battery (no exact-alarm permission needed).
class WaterReminders {
  WaterReminders([FlutterLocalNotificationsPlugin? plugin]) : _plugin = plugin ?? FlutterLocalNotificationsPlugin();

  final FlutterLocalNotificationsPlugin _plugin;
  bool _initialised = false;

  static const _baseId = 5000;
  static const _maxSlots = 16;
  static const startHour = 9;
  static const endHour = 21;

  Future<void> _init() async {
    if (_initialised) return;
    await _plugin.initialize(settings: const InitializationSettings(android: AndroidInitializationSettings('@mipmap/ic_launcher')));
    _initialised = true;
  }

  /// Reschedules from scratch; [intervalMinutes] null or [enabled] false cancels all.
  Future<void> apply({required bool enabled, required int intervalMinutes, required tz.Location location}) async {
    await _init();
    for (var i = 0; i < _maxSlots; i++) {
      await _plugin.cancel(id: _baseId + i);
    }
    if (!enabled) return;

    final times = slots(intervalMinutes);
    final now = tz.TZDateTime.now(location);
    for (var i = 0; i < times.length; i++) {
      final (h, m) = times[i];
      var at = tz.TZDateTime(location, now.year, now.month, now.day, h, m);
      if (!at.isAfter(now)) at = at.add(const Duration(days: 1));
      await _plugin.zonedSchedule(
        id: _baseId + i,
        scheduledDate: at,
        title: 'وقت یک لیوان آب',
        body: 'مصرف آب امروزت را در گام‌یار ثبت کن.',
        notificationDetails: const NotificationDetails(
          android: AndroidNotificationDetails('water_reminder', 'یادآوری آب', channelDescription: 'یادآوری اختیاری نوشیدن آب', importance: Importance.defaultImportance),
        ),
        androidScheduleMode: AndroidScheduleMode.inexactAllowWhileIdle,
        matchDateTimeComponents: DateTimeComponents.time,
      );
    }
  }

  /// Times of day (h, m) from 09:00 to 21:00 every [intervalMinutes].
  static List<(int, int)> slots(int intervalMinutes) {
    final step = intervalMinutes.clamp(30, 480);
    final out = <(int, int)>[];
    for (var t = startHour * 60; t <= endHour * 60 && out.length < _maxSlots; t += step) {
      out.add((t ~/ 60, t % 60));
    }
    return out;
  }
}

final waterRemindersProvider = Provider<WaterReminders>((ref) => WaterReminders());
