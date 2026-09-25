import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/app/router.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/providers.dart';
import 'package:gamyar/core/storage/secure_store.dart';
import 'package:gamyar/features/auth/application/session_controller.dart';
import 'package:gamyar/features/notifications/application/notification_routes.dart';
import 'package:gamyar/features/notifications/application/push_service.dart';
import 'package:gamyar/features/profile/data/me.dart';
import 'package:go_router/go_router.dart';

import '../../helpers/test_app.dart';

final me = Me.fromJson({
  'id': '01h', 'public_name': 'سارا', 'display_name': 'سارا', 'status': 'active', 'level': 2, 'xp': 10,
  'referral_code': 'X', 'timezone': 'Asia/Tehran', 'profile': <String, dynamic>{}, 'settings': <String, dynamic>{},
});

class _Session extends SessionController {
  @override
  Future<SessionState> build() async => SessionAuthenticated(me);
}

class _Api extends ApiClient {
  _Api(SecureStore store) : super(store: store, deviceKey: FakeDeviceKey(), appVersion: '1');
  final puts = <String>[];

  @override
  Future<Map<String, dynamic>> put(String path, {Object? data, Options? options}) async {
    puts.add('$path ${jsonEncode(data)}');
    return const {};
  }
}

class _Messaging implements PushMessaging {
  _Messaging({this.enabled = true, this.provider = 'fcm'});
  final bool enabled;
  @override
  final String provider;
  final refresh = StreamController<String>.broadcast();
  final fg = StreamController<PushMessage>.broadcast();
  final openedCtl = StreamController<PushMessage>.broadcast();
  PushMessage? launch;
  bool permissionAsked = false;

  @override
  Future<bool> start() async => enabled;
  @override
  Future<bool> requestPermission() async => permissionAsked = true;
  @override
  Future<String?> token() async => 'tok-1';
  @override
  Stream<String> get tokenRefresh => refresh.stream;
  @override
  Stream<PushMessage> get foreground => fg.stream;
  @override
  Stream<PushMessage> get opened => openedCtl.stream;
  @override
  Future<PushMessage?> launchMessage() async => launch;
}

void main() {
  test('notification data maps to app routes, never to arbitrary paths', () {
    expect(notificationRoute({'type': 'order', 'id': 'o1'}), '/orders/o1');
    expect(notificationRoute({'type': 'support', 'id': 't9'}), '/support/t9');
    expect(notificationRoute({'type': 'goal'}), '/rewards');
    expect(notificationRoute({'type': 'order'}), isNull);
    expect(notificationRoute({'type': 'url', 'id': 'https://evil'}), isNull);
    expect(isTabRoute('/rewards'), isTrue);
    expect(isTabRoute('/orders/o1'), isFalse);
  });

  Future<(ProviderContainer, _Api, _Messaging, GoRouter, List<PushMessage>)> pump(WidgetTester tester, _Messaging messaging) async {
    final store = MemorySecureStore();
    final api = _Api(store);
    final shown = <PushMessage>[];
    final router = GoRouter(initialLocation: '/home', routes: [
      GoRoute(path: '/home', builder: (_, _) => const Text('home')),
      GoRoute(path: '/rewards', builder: (_, _) => const Text('rewards')),
      GoRoute(path: '/orders/:id', builder: (_, s) => Text('order ${s.pathParameters['id']}')),
    ]);
    await tester.pumpWidget(testRouterApp(router, overrides: [
      sessionProvider.overrideWith(_Session.new),
      secureStoreProvider.overrideWithValue(store),
      apiClientProvider.overrideWithValue(api),
      pushMessagingProvider.overrideWithValue(messaging),
      foregroundPresenterProvider.overrideWithValue((m) async => shown.add(m)),
      routerProvider.overrideWithValue(router),
    ]));
    final container = ProviderScope.containerOf(tester.element(find.text('home')));
    container.read(pushControllerProvider);
    await tester.pumpAndSettle();
    return (container, api, messaging, router, shown);
  }

  testWidgets('registers the token once, again only when it changes', (tester) async {
    final (_, api, m, _, _) = await pump(tester, _Messaging());
    expect(m.permissionAsked, isTrue);
    expect(api.puts, ['/devices/push-token {"provider":"fcm","token":"tok-1"}']);

    m.refresh.add('tok-1');
    await tester.pumpAndSettle();
    expect(api.puts, hasLength(1));

    m.refresh.add('tok-2');
    await tester.pumpAndSettle();
    expect(api.puts.last, '/devices/push-token {"provider":"fcm","token":"tok-2"}');
  });

  testWidgets('tapping a push opens its target; foreground pushes are shown locally', (tester) async {
    final (_, _, m, _, shown) = await pump(tester, _Messaging());

    m.openedCtl.add((title: 'سفارش', body: 'ارسال شد', data: {'type': 'order', 'id': 'o7'}));
    await tester.pumpAndSettle();
    expect(find.text('order o7'), findsOneWidget);

    m.fg.add((title: 'چالش', body: 'شروع شد', data: {'type': 'goal'}));
    await tester.pumpAndSettle();
    expect(shown.single.title, 'چالش');
  });

  testWidgets('Bazaar/Myket builds register their Pushe device id', (tester) async {
    final (_, api, _, _, _) = await pump(tester, _Messaging(provider: 'pushe'));
    expect(api.puts, ['/devices/push-token {"provider":"pushe","token":"tok-1"}']);
  });

  testWidgets('a build without push config registers nothing', (tester) async {
    final (_, api, m, _, _) = await pump(tester, _Messaging(enabled: false));
    expect(api.puts, isEmpty);
    expect(m.permissionAsked, isFalse);
  });
}
