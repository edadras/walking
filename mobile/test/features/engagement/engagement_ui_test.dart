import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/providers.dart';
import 'package:gamyar/core/storage/secure_store.dart';
import 'package:gamyar/features/challenges/presentation/challenges_page.dart';
import 'package:gamyar/features/gamification/data/gamification_models.dart';
import 'package:gamyar/features/gamification/data/gamification_repository.dart';
import 'package:gamyar/features/gamification/presentation/achievements_page.dart';
import 'package:gamyar/features/gamification/presentation/leaderboard_page.dart';
import 'package:gamyar/features/health/data/health_models.dart';
import 'package:gamyar/features/health/data/health_repository.dart';
import 'package:gamyar/features/health/presentation/health_page.dart';
import 'package:gamyar/features/notifications/presentation/inbox_page.dart';

import '../../helpers/test_app.dart';

/// Scripted API: GET responses by path, and a log of POSTs.
class ScriptedApi extends ApiClient {
  ScriptedApi(this.routes) : super(store: MemorySecureStore(), deviceKey: FakeDeviceKey(), appVersion: '1');

  final Map<String, Map<String, dynamic> Function()> routes;
  final posts = <String>[];

  @override
  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query, Options? options}) async => routes[path]!();

  @override
  Future<Map<String, dynamic>> post(String path, {Object? data, Options? options}) async {
    posts.add(path);
    return routes['POST $path']!();
  }
}

Map<String, dynamic> challengeJson({bool joined = false, int progress = 0}) => {
      'id': 'ch1', 'title': 'ده هزار قدم هفته', 'description': 'در این هفته ۵۰ هزار قدم بردار.', 'image_url': null,
      'type': 'weekly', 'type_label': 'هفتگی', 'metric': 'steps', 'target': 50000, 'reward_points': 100, 'reward_xp': 200,
      'sponsored': false, 'starts_at': '2026-09-20T00:00:00Z', 'ends_at': '2026-09-27T00:00:00Z', 'participants': 12,
      'status': 'running', 'joinable': !joined, 'joined': joined, 'progress': progress, 'completed_at': null,
      'top': [if (joined) {'rank': 1, 'name': 'مریم', 'progress': progress, 'completed': false, 'is_me': true}],
    };

void main() {
  testWidgets('leaderboard lists entries, highlights me, and explains hidden state', (tester) async {
    Leaderboard board(String period) => Leaderboard.fromJson({
          'entries': [
            {'rank': 1, 'name': 'علی', 'avatar_url': null, 'level': 4, 'steps': 18000, 'is_me': false},
            {'rank': 2, 'name': 'مریم', 'avatar_url': null, 'level': 3, 'steps': 12000, 'is_me': true},
          ],
          'me': {'rank': 2, 'name': 'مریم', 'avatar_url': null, 'level': 3, 'steps': 12000, 'visible': true},
        });
    await tester.pumpWidget(testApp(const LeaderboardPage(), overrides: [leaderboardProvider.overrideWith((ref, p) async => board(p))]));
    await tester.pumpAndSettle();

    expect(find.text('علی'), findsOneWidget);
    expect(find.text('مریم (شما)'), findsOneWidget);
    expect(find.text('۱۸٬۰۰۰ قدم'), findsOneWidget);
  });

  testWidgets('hidden users see the privacy note instead of their row', (tester) async {
    await tester.pumpWidget(testApp(const LeaderboardPage(), overrides: [
      leaderboardProvider.overrideWith((ref, p) async => Leaderboard.fromJson({'entries': [], 'me': {'rank': 0, 'name': 'x', 'level': 1, 'steps': 0, 'visible': false}})),
    ]));
    await tester.pumpAndSettle();
    expect(find.textContaining('نمایش شما در رتبه‌بندی خاموش است'), findsOneWidget);
    expect(find.text('هنوز کسی در این دوره رتبه ندارد.'), findsOneWidget);
  });

  testWidgets('achievements show unlocked count and progress for locked ones', (tester) async {
    await tester.pumpWidget(testApp(const AchievementsPage(), overrides: [
      achievementsProvider.overrideWith((_) async => [
            AchievementItem.fromJson({'key': 'a', 'name': 'اولین ۵٬۰۰۰', 'description': 'd', 'icon': 'footsteps', 'threshold': 5000, 'progress': 5000, 'unlocked_at': '2026-09-20T10:00:00Z', 'xp_reward': 50, 'point_reward': 0}),
            AchievementItem.fromJson({'key': 'b', 'name': '۷ روز متوالی', 'description': 'd', 'icon': 'chain', 'threshold': 7, 'progress': 3, 'unlocked_at': null, 'xp_reward': 150, 'point_reward': 30}),
          ]),
      progressProvider.overrideWith((_) async => (
            LevelProgress.fromJson({'level': 3, 'title': 'رهرو', 'xp': 420, 'current_min': 400, 'next_min': 750}),
            StreakWeek.fromJson({'current': 2, 'week': []}),
          )),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('۱ از ۲ دستاورد'), findsOneWidget);
    expect(find.text('۳ / ۷'), findsOneWidget);
    expect(find.text('سطح ۳ · رهرو'), findsOneWidget);
    expect(find.text('۴۲۰ از ۷۵۰ XP'), findsOneWidget);
  });

  testWidgets('joining a challenge posts a signed join and shows progress', (tester) async {
    var joined = false;
    final api = ScriptedApi({
      '/challenges/ch1': () => {'data': challengeJson(joined: joined, progress: joined ? 12500 : 0)},
      'POST /challenges/ch1/join': () {
        joined = true;
        return {'data': challengeJson(joined: true)};
      },
    });
    await tester.pumpWidget(testApp(const ChallengeDetailPage(id: 'ch1'), overrides: [apiClientProvider.overrideWithValue(api)]));
    await tester.pumpAndSettle();

    expect(find.text('هدف: ۵۰٬۰۰۰ قدم'), findsOneWidget);
    await tester.tap(find.text('شرکت در چالش'));
    await tester.pumpAndSettle();

    expect(api.posts, ['/challenges/ch1/join']);
    expect(find.text('شرکت در چالش'), findsNothing);
    expect(find.text('۱۲٬۵۰۰ قدم · ۲۵٪'), findsOneWidget);
    expect(find.text('مریم (شما)'), findsOneWidget);
  });

  testWidgets('inbox marks everything read', (tester) async {
    var read = false;
    final api = ScriptedApi({
      '/notifications': () => {
            'data': [
              {'id': 'n1', 'category': 'reward_received', 'title': 'دستاورد جدید', 'body': 'آفرین', 'read': read, 'created_at': '2026-09-24T08:00:00Z', 'data': {'type': 'achievement'}},
            ],
          },
      'POST /notifications/read': () {
        read = true;
        return {'data': null};
      },
    });
    await tester.pumpWidget(testApp(const InboxPage(), overrides: [apiClientProvider.overrideWithValue(api)]));
    await tester.pumpAndSettle();

    expect(find.text('دستاورد جدید'), findsOneWidget);
    await tester.tap(find.text('خواندن همه'));
    await tester.pumpAndSettle();
    expect(api.posts, ['/notifications/read']);
    expect(find.text('خواندن همه'), findsNothing);
  });

  testWidgets('weekly report shows change against last week', (tester) async {
    await tester.pumpWidget(testApp(const WeeklyReportPage(), overrides: [
      weeklyReportProvider.overrideWith((_) async => WeeklyReport.fromJson({
            'week_start': '2026-09-20', 'days_elapsed': 5, 'steps': 42000, 'distance_m': 30000, 'calories_kcal': 1500.0,
            'points': 320, 'active_minutes': 310, 'goal_days': 3, 'previous_steps': 35000, 'change_percent': 20,
          })),
    ]));
    await tester.pumpAndSettle();
    expect(find.text('۴۲٬۰۰۰ قدم'), findsOneWidget);
    expect(find.textContaining('۲۰٪'), findsOneWidget);
    expect(find.text('+۳۲۰ امتیاز', findRichText: true), findsOneWidget);
  });

  test('water day parses logs and suggestion', () {
    final d = WaterDay.fromJson({
      'goal_ml': 2000, 'glass_ml': 250, 'total_ml': 750, 'glasses': 3, 'goal_glasses': 8, 'suggested_goal_ml': 2300,
      'reminder_enabled': true, 'reminder_interval_min': 90,
      'logs': [{'id': 1, 'amount_ml': 250, 'logged_at': '2026-09-24T07:00:00Z'}],
    });
    expect(d.glasses, 3);
    expect(d.logs.single.amountMl, 250);
    expect(d.suggestedGoalMl, 2300);
  });
}
