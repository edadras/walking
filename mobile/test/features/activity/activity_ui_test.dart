import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/features/activity/application/activity_providers.dart';
import 'package:gamyar/features/activity/application/tracking_service.dart';
import 'package:gamyar/features/activity/data/activity_models.dart';
import 'package:gamyar/features/activity/presentation/activity_page.dart';
import 'package:gamyar/features/auth/application/session_controller.dart';
import 'package:gamyar/features/home/presentation/home_page.dart';
import 'package:gamyar/features/profile/data/me.dart';
import 'package:timezone/data/latest_10y.dart' as tz_data;

import '../../helpers/test_app.dart';
import 'tracking_service_test.dart' show FakePlatform;

final me = Me.fromJson({
  'id': '01h', 'public_name': 'سارا', 'display_name': 'سارا', 'status': 'active', 'level': 3, 'xp': 10,
  'referral_code': 'X', 'timezone': 'Asia/Tehran', 'profile': <String, dynamic>{},
  'settings': <String, dynamic>{'daily_step_goal': 10000},
});

HomeData homeData({int steps = 6000, int pending = 1200}) => HomeData.fromJson({
      'today': {'date': '2026-09-24', 'steps': steps, 'verified_steps': steps - pending, 'pending_steps': pending, 'goal': 10000, 'distance_m': 4700, 'calories_kcal': 286.4, 'active_minutes': 63, 'goal_reached': false},
      'week': [for (var i = 0; i < 7; i++) {'date': '2026-09-${18 + i}', 'steps': 5000 + i * 500, 'goal': 10000}],
    });

class FakeHome extends HomeController {
  FakeHome(this.view);

  final HomeView view;

  @override
  Future<HomeView> build() async => view;
}

void main() {
  setUpAll(tz_data.initializeTimeZones);

  List overrides({required HomeView view, TrackingAvailability availability = TrackingAvailability.ready}) => [
        meProvider.overrideWithValue(me),
        homeProvider.overrideWith(() => FakeHome(view)),
        trackingAvailabilityProvider.overrideWith((_) async => availability),
        stepPlatformProvider.overrideWithValue(FakePlatform()),
      ];

  testWidgets('home shows server steps plus steps still queued on the device', (tester) async {
    await tester.pumpWidget(testApp(const HomePage(), overrides: overrides(view: HomeView(data: homeData(), unsyncedSteps: 840))));
    await tester.pumpAndSettle();

    expect(find.text('۶٬۸۴۰'), findsOneWidget); // 6000 + 840
    expect(find.text('۳٬۱۶۰ قدم تا هدف امروز'), findsOneWidget);
    expect(find.text('۲٬۰۴۰ قدم در حال بررسی'), findsOneWidget); // 1200 server-pending + 840 local
    expect(find.textContaining('۴٫۷', findRichText: true), findsOneWidget);
    expect(find.text('شروع پیاده‌روی'), findsOneWidget);
    expect(find.text('ثبت خودکار قدم‌ها خاموش است'), findsNothing);
  });

  testWidgets('home asks for the activity permission in context when tracking is off', (tester) async {
    await tester.pumpWidget(testApp(
      const HomePage(),
      overrides: overrides(view: HomeView(data: homeData(), unsyncedSteps: 0), availability: TrackingAvailability.needsPermission),
    ));
    await tester.pumpAndSettle();

    expect(find.text('ثبت خودکار قدم‌ها خاموش است'), findsOneWidget);
    expect(find.text('فعال‌سازی'), findsOneWidget);
  });

  testWidgets('activity timeline lists sessions newest first with their status', (tester) async {
    final day = DayActivity.fromJson({
      'date': '2026-09-24',
      'summary': {'date': '2026-09-24', 'steps': 3340, 'verified_steps': 1240, 'goal': 10000, 'goal_reached': false, 'distance_m': 2300, 'calories_kcal': 120, 'active_minutes': 34, 'points': 0},
      'hourly': [for (var h = 0; h < 24; h++) h == 8 ? 1240 : (h == 12 ? 2100 : 0)],
      'sessions': [
        {'id': 'a', 'kind': 'passive', 'started_at': '2026-09-24T04:50:00Z', 'ended_at': '2026-09-24T05:20:00Z', 'steps': 1240, 'verified_steps': 1240, 'distance_m': 800, 'duration_s': 1800, 'active_duration_s': 700, 'calories_kcal': 40, 'activity_type': 'walking', 'status': 'verified', 'status_label': 'تأییدشده'},
        {'id': 'b', 'kind': 'active', 'started_at': '2026-09-24T08:40:00Z', 'ended_at': '2026-09-24T09:10:00Z', 'steps': 2100, 'verified_steps': null, 'distance_m': 1500, 'duration_s': 1800, 'active_duration_s': 1800, 'calories_kcal': 80, 'activity_type': 'walking', 'status': 'submitted', 'status_label': 'در حال بررسی'},
      ],
    });

    await tester.pumpWidget(testApp(const ActivityPage(), overrides: [
      meProvider.overrideWithValue(me),
      dayActivityProvider.overrideWith((ref, date) async => day),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('۲٬۱۰۰ قدم'), findsOneWidget);
    expect(find.text('۱٬۲۴۰ قدم'), findsOneWidget);
    expect(find.text('در حال بررسی'), findsOneWidget);
    expect(find.text('تأییدشده'), findsOneWidget);
    expect(tester.getTopLeft(find.text('۲٬۱۰۰ قدم')).dy, lessThan(tester.getTopLeft(find.text('۱٬۲۴۰ قدم')).dy));
  });

  testWidgets('activity shows a designed empty state for a day without steps', (tester) async {
    await tester.pumpWidget(testApp(const ActivityPage(), overrides: [
      meProvider.overrideWithValue(me),
      dayActivityProvider.overrideWith((ref, date) async => DayActivity.fromJson({'date': '2026-09-24', 'summary': null, 'hourly': List.filled(24, 0), 'sessions': <Object>[]})),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('هنوز قدمی ثبت نشده'), findsOneWidget);
  });
}
