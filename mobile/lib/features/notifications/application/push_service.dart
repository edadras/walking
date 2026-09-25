import 'dart:async';
import 'dart:convert';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/router.dart';
import '../../../core/config/env.dart';
import '../../../core/notifications/local_notifications.dart';
import '../../../core/providers.dart';
import '../../../core/storage/secure_store.dart';
import '../../auth/application/session_controller.dart';
import 'notification_routes.dart';

/// A received push: what to show and the (string-only) data used for routing.
typedef PushMessage = ({String? title, String? body, Map<String, String> data});

/// The platform push SDK, behind an interface so the controller is testable
/// and the app runs unchanged in builds without Firebase config.
abstract class PushMessaging {
  /// Server-side provider key: `fcm` or `pushe`.
  String get provider;

  /// False when push isn't configured for this build or the SDK failed to start.
  Future<bool> start();
  Future<bool> requestPermission();
  Future<String?> token();
  Stream<String> get tokenRefresh;
  Stream<PushMessage> get foreground;
  Stream<PushMessage> get opened;
  Future<PushMessage?> launchMessage();
}

class FirebasePushMessaging implements PushMessaging {
  @override
  String get provider => 'fcm';

  PushMessage _map(RemoteMessage m) => (title: m.notification?.title, body: m.notification?.body, data: m.data.map((k, v) => MapEntry(k, '$v')));

  @override
  Future<bool> start() async {
    if (!Env.pushConfigured) return false;
    try {
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp(
          options: const FirebaseOptions(apiKey: Env.fcmApiKey, appId: Env.fcmAppId, messagingSenderId: Env.fcmSenderId, projectId: Env.fcmProjectId),
        );
      }
      // The inbox is the record; FCM analytics/auto-init aren't needed beyond delivery.
      await FirebaseMessaging.instance.setAutoInitEnabled(true);
      return true;
    } catch (e) {
      if (kDebugMode) debugPrint('Push unavailable: $e');
      return false;
    }
  }

  @override
  Future<bool> requestPermission() async {
    final s = await FirebaseMessaging.instance.requestPermission();
    return s.authorizationStatus == AuthorizationStatus.authorized || s.authorizationStatus == AuthorizationStatus.provisional;
  }

  @override
  Future<String?> token() => FirebaseMessaging.instance.getToken();

  @override
  Stream<String> get tokenRefresh => FirebaseMessaging.instance.onTokenRefresh;

  @override
  Stream<PushMessage> get foreground => FirebaseMessaging.onMessage.map(_map);

  @override
  Stream<PushMessage> get opened => FirebaseMessaging.onMessageOpenedApp.map(_map);

  @override
  Future<PushMessage?> launchMessage() async {
    final m = await FirebaseMessaging.instance.getInitialMessage();
    return m == null ? null : _map(m);
  }
}

/// Pushe (Bazaar/Myket builds): the SDK displays notifications itself and hands
/// taps to us through a native channel; the push token is the Pushe device id.
class PushePushMessaging implements PushMessaging {
  static const _methods = MethodChannel('ir.gamyar.app/pushe');
  static const _clicks = EventChannel('ir.gamyar.app/pushe/clicks');

  PushMessage _map(Object? data) => (title: null, body: null, data: Map<String, String>.from((data as Map?) ?? const {}));

  @override
  String get provider => 'pushe';

  @override
  Future<bool> start() async {
    try {
      return await _methods.invokeMethod<bool>('available') ?? false;
    } on MissingPluginException {
      return false;
    }
  }

  /// Notification permission is already enforced by the permissions gate.
  @override
  Future<bool> requestPermission() async => true;

  @override
  Future<String?> token() => _methods.invokeMethod<String>('deviceId');

  /// The Pushe device id is the Android id: it doesn't rotate.
  @override
  Stream<String> get tokenRefresh => const Stream.empty();

  @override
  Stream<PushMessage> get foreground => const Stream.empty();

  @override
  Stream<PushMessage> get opened => _clicks.receiveBroadcastStream().map(_map);

  @override
  Future<PushMessage?> launchMessage() async {
    final data = await _methods.invokeMethod<Object?>('launchClick');
    return data == null ? null : _map(data);
  }
}

final pushMessagingProvider = Provider<PushMessaging>((ref) => Env.store == 'play' || Env.store == 'direct' ? FirebasePushMessaging() : PushePushMessaging());

/// Shows a push that arrived while the app is open (FCM only displays in the background).
typedef ForegroundPresenter = Future<void> Function(PushMessage message);

final foregroundPresenterProvider = Provider<ForegroundPresenter>((ref) => (m) async {
      await LocalNotifications.ensureInitialized();
      final ch = LocalNotifications.generalChannel;
      await LocalNotifications.plugin.show(
        id: m.hashCode & 0x7fffffff,
        title: m.title,
        body: m.body,
        notificationDetails: NotificationDetails(android: AndroidNotificationDetails(ch.id, ch.name, channelDescription: ch.description)),
        payload: jsonEncode(m.data),
      );
    });

/// Registers this device's push token with the server once signed in, keeps it
/// fresh, and turns notification taps into navigation.
class PushController {
  PushController(this._ref);

  final Ref _ref;
  final _subs = <StreamSubscription<Object?>>[];
  bool _started = false;
  String? _pendingRoute;

  static const _kSentToken = 'push_token_sent';

  PushMessaging get _messaging => _ref.read(pushMessagingProvider);
  SecureStore get _store => _ref.read(secureStoreProvider);

  /// Called when a session becomes authenticated. Idempotent.
  Future<void> start() async {
    if (_started) return;
    _started = true;

    _subs.add(LocalNotifications.taps.listen((payload) => _openPayload(payload)));
    unawaited(LocalNotifications.launchPayload().then(_openPayload).catchError((_) {}));

    if (!await _messaging.start()) return;
    _subs
      ..add(_messaging.tokenRefresh.listen(_register))
      ..add(_messaging.foreground.listen((m) => _ref.read(foregroundPresenterProvider)(m)))
      ..add(_messaging.opened.listen((m) => open(m.data)));
    final launch = await _messaging.launchMessage();
    if (launch != null) open(launch.data);

    // Android 13+ asks once; a refusal only means no system banners (the inbox still fills).
    await _messaging.requestPermission();
    final token = await _messaging.token();
    if (token != null) await _register(token);
  }

  Future<void> _register(String token) async {
    if (await _store.read(_kSentToken) == token) return;
    try {
      await _ref.read(apiClientProvider).put('/devices/push-token', data: {'provider': _messaging.provider, 'token': token});
      await _store.write(_kSentToken, token);
    } catch (_) {
      // Retried on the next start or token refresh.
    }
  }

  /// A new account on this device must re-register even if the token is unchanged.
  Future<void> reset() async {
    final store = _store;
    await dispose();
    await store.delete(_kSentToken);
  }

  Future<void> dispose() async {
    final subs = [..._subs];
    _subs.clear();
    _started = false;
    for (final s in subs) {
      await s.cancel();
    }
  }

  void _openPayload(String? payload) {
    if (payload == null || payload.isEmpty) return;
    try {
      open(Map<String, Object?>.from(jsonDecode(payload) as Map));
    } catch (_) {}
  }

  /// Navigates to the notification's target, or queues it until the router is ready.
  void open(Map<String, Object?> data) {
    final route = notificationRoute(data);
    if (route == null) return;
    _pendingRoute = route;
    _flush();
  }

  void _flush() {
    final route = _pendingRoute;
    if (route == null || _ref.read(sessionProvider).value is! SessionAuthenticated) return;
    _pendingRoute = null;
    final router = _ref.read(routerProvider);
    // Land on the tab first so Back from a detail page returns somewhere sensible.
    if (isTabRoute(route)) {
      router.go(route);
    } else {
      router.go('/home');
      router.push(route);
    }
  }

  void onSessionChanged() => _flush();
}

final pushControllerProvider = Provider<PushController>((ref) {
  final controller = PushController(ref);
  ref.listen(sessionProvider, (prev, next) {
    final wasIn = prev?.value is SessionAuthenticated;
    final isIn = next.value is SessionAuthenticated;
    if (isIn) {
      unawaited(controller.start());
      controller.onSessionChanged();
    } else if (wasIn) {
      unawaited(controller.reset());
    }
  }, fireImmediately: true);
  ref.onDispose(() => unawaited(controller.dispose()));
  return controller;
});
