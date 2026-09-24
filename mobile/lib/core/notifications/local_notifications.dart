import 'dart:async';

import 'package:flutter_local_notifications/flutter_local_notifications.dart';

/// One place that initialises the local-notifications plugin. The plugin keeps a
/// single tap callback, so water reminders and foreground pushes share it; taps
/// are fanned out as payload strings.
abstract final class LocalNotifications {
  static final plugin = FlutterLocalNotificationsPlugin();
  static final _taps = StreamController<String?>.broadcast();
  static final _initialised = Expando<bool>();

  /// Android channel the server's FCM payloads target (`channel_id: general`).
  static const generalChannel = AndroidNotificationChannel('general', 'اعلان‌های گام‌یار', description: 'پاداش‌ها، سفارش‌ها، چالش‌ها و پشتیبانی');

  static Stream<String?> get taps => _taps.stream;

  static Future<void> ensureInitialized([FlutterLocalNotificationsPlugin? instance]) async {
    final p = instance ?? plugin;
    if (_initialised[p] == true) return;
    await p.initialize(
      settings: const InitializationSettings(android: AndroidInitializationSettings('@mipmap/ic_launcher')),
      onDidReceiveNotificationResponse: (r) => _taps.add(r.payload),
    );
    await p.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()?.createNotificationChannel(generalChannel);
    _initialised[p] = true;
  }

  /// Payload of the local notification that cold-started the app, if any.
  static Future<String?> launchPayload() async {
    await ensureInitialized();
    final details = await plugin.getNotificationAppLaunchDetails();
    return details?.didNotificationLaunchApp == true ? details!.notificationResponse?.payload : null;
  }
}
