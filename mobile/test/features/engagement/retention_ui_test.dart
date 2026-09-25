import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/network/api_exception.dart';
import 'package:gamyar/core/providers.dart';
import 'package:gamyar/core/storage/secure_store.dart';
import 'package:gamyar/features/gamification/data/gamification_models.dart';
import 'package:gamyar/features/gamification/presentation/streak_sheet.dart';
import 'package:gamyar/features/quests/presentation/quests_page.dart';
import 'package:gamyar/features/social/presentation/friends_page.dart';

import '../../helpers/test_app.dart';

/// Minimal in-memory server for the retention screens.
class _Api extends ApiClient {
  _Api() : super(store: MemorySecureStore(), deviceKey: FakeDeviceKey(), appVersion: '1');

  final calls = <String>[];
  bool questClaimed = false;
  int freezes = 0;
  final friends = <Map<String, dynamic>>[];
  final incoming = <Map<String, dynamic>>[
    {'id': 7, 'user_id': '01HZZZZZZZZZZZZZZZZZZZZZZA', 'name': 'رضا', 'avatar_url': null, 'level': 3},
  ];

  Map<String, dynamic> _quests() => {
        'data': [
          {'key': 'daily_steps_6k', 'title': 'قدم‌های امروز', 'description': 'امروز ۶ هزار قدم', 'period': 'daily', 'metric': 'steps', 'unit': 'قدم', 'target': 6000, 'progress': 6000, 'reward_points': 10, 'reward_xp': 20, 'claimed': questClaimed, 'claimable': !questClaimed, 'ends_on': '2026-09-24'},
          {'key': 'weekly_goal_5', 'title': 'پنج روز هدف', 'description': null, 'period': 'weekly', 'metric': 'goal_days', 'unit': 'روز', 'target': 5, 'progress': 2, 'reward_points': 60, 'reward_xp': 100, 'claimed': false, 'claimable': false, 'ends_on': '2026-09-25'},
        ],
      };

  Map<String, dynamic> _friends() => {
        'data': {
          'code': 'AB12CD34',
          'ranking': [
            ...friends,
            {'user_id': '01HZZZZZZZZZZZZZZZZZZZZZZM', 'name': 'سارا', 'avatar_url': null, 'level': 2, 'week_steps': 12000, 'is_me': true, 'friendship_id': null},
          ],
          'incoming': incoming,
          'outgoing': <Map<String, dynamic>>[],
        },
      };

  Map<String, dynamic> _streak() => {
        'current': 5,
        'longest': 9,
        'week': <Map<String, dynamic>>[],
        'freezes': {'owned': freezes, 'max': 2, 'price': 300, 'can_buy': freezes < 2},
      };

  @override
  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query, Options? options}) async => switch (path) {
        '/quests' => _quests(),
        '/friends' => _friends(),
        '/friend-challenges' => {'data': <Object>[]},
        _ => throw ApiException(code: 'not_found', message: path),
      };

  @override
  Future<Map<String, dynamic>> post(String path, {Object? data, Options? options}) async {
    calls.add(path);
    switch (path) {
      case '/quests/daily_steps_6k/claim':
        questClaimed = true;
        return _quests();
      case '/streak/freezes':
        freezes++;
        return {'data': _streak()};
      case '/friends/7/accept':
        incoming.clear();
        friends.add({'user_id': '01HZZZZZZZZZZZZZZZZZZZZZZA', 'name': 'رضا', 'avatar_url': null, 'level': 3, 'week_steps': 15000, 'is_me': false, 'friendship_id': 7});
        return _friends();
      case '/friends':
        if ((data! as Map)['code'] == 'XXXX') throw const ApiException(code: 'friend_not_found', message: 'کاربری با این کد پیدا نشد.');
        return _friends();
    }
    return const {};
  }
}

void main() {
  late _Api api;
  setUp(() => api = _Api());
  Future<void> pump(WidgetTester tester, Widget page) async {
    tester.view
      ..physicalSize = const Size(1080, 2800)
      ..devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(testApp(page, overrides: [apiClientProvider.overrideWithValue(api)]));
    await tester.pumpAndSettle();
  }

  testWidgets('quests: completed ones can be claimed once, unfinished show progress', (tester) async {
    await pump(tester, const QuestsPage());
    expect(find.text('۶٬۰۰۰ / ۶٬۰۰۰ قدم'), findsOneWidget);
    expect(find.text('۲ / ۵ روز'), findsOneWidget);
    expect(find.text('دریافت'), findsOneWidget, reason: 'only the finished quest');

    await tester.tap(find.text('دریافت'));
    await tester.pumpAndSettle();
    expect(api.calls, ['/quests/daily_steps_6k/claim']);
    expect(find.text('دریافت شد'), findsOneWidget);
  });

  testWidgets('streak sheet buys freezes until the cap', (tester) async {
    await pump(tester, Scaffold(body: Builder(builder: (c) => TextButton(onPressed: () => showStreakSheet(c, StreakWeek.fromJson(api._streak())), child: const Text('open')))));
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    expect(find.text('طولانی‌ترین زنجیره: ۹ روز'), findsOneWidget);

    await tester.tap(find.text('خرید محافظ با ۳۰۰ امتیاز'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('خرید محافظ با ۳۰۰ امتیاز'));
    await tester.pumpAndSettle();
    expect(api.calls, ['/streak/freezes', '/streak/freezes']);
    expect(find.text('ظرفیت محافظ‌هایت پر است.'), findsOneWidget);
  });

  testWidgets('friends: accept a request, then the weekly ranking includes them', (tester) async {
    await pump(tester, const FriendsPage());
    expect(find.text('AB12CD34'), findsOneWidget);
    expect(find.text('درخواست‌های دوستی'), findsOneWidget);
    expect(find.textContaining('هنوز دوستی اضافه نکرده‌ای'), findsOneWidget);

    await tester.tap(find.byTooltip('پذیرفتن'));
    await tester.pumpAndSettle();
    expect(api.calls, ['/friends/7/accept']);
    expect(find.text('۱۵٬۰۰۰ قدم این هفته'), findsOneWidget);
    expect(find.text('رقابت دوستانه'), findsOneWidget, reason: 'races unlock with the first friend');

    await tester.enterText(find.byType(TextField), 'XXXX');
    await tester.tap(find.text('افزودن'));
    await tester.pumpAndSettle();
    expect(find.text('کاربری با این کد پیدا نشد.'), findsOneWidget);
  });
}
