import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/widgets/app_bottom_nav.dart';
import 'package:gamyar/core/widgets/state_views.dart';
import 'package:gamyar/core/widgets/step_ring.dart';
import 'package:gamyar/core/network/api_exception.dart';

import '../helpers/test_app.dart';

void main() {
  testWidgets('StepRing exposes an accessible Persian summary', (tester) async {
    await tester.pumpWidget(testApp(const Scaffold(body: Center(child: StepRing(steps: 6840, goal: 10000)))));
    await tester.pumpAndSettle();

    expect(find.bySemanticsLabel('۶٬۸۴۰ قدم از ۱۰٬۰۰۰، ۶۸٪'), findsOneWidget);
    expect(find.text('۶٬۸۴۰'), findsOneWidget);
    expect(find.text('۶۸٪'), findsOneWidget);
  });

  testWidgets('StepRing celebrates a completed goal', (tester) async {
    await tester.pumpWidget(testApp(const Scaffold(body: Center(child: StepRing(steps: 12000, goal: 10000)))));
    await tester.pumpAndSettle();

    expect(find.text('هدف امروز کامل شد'), findsOneWidget);
  });

  testWidgets('app is laid out right-to-left', (tester) async {
    late TextDirection direction;
    await tester.pumpWidget(testApp(Builder(builder: (c) {
      direction = Directionality.of(c);
      return const SizedBox();
    })));

    expect(direction, TextDirection.rtl);
  });

  testWidgets('bottom nav: first item sits on the right in RTL and reports taps', (tester) async {
    var tapped = -1;
    await tester.pumpWidget(testApp(Scaffold(
      bottomNavigationBar: AppBottomNav(
        currentIndex: 0,
        onTap: (i) => tapped = i,
        items: const [
          AppNavItem(label: 'خانه', icon: Icons.home_outlined, activeIcon: Icons.home),
          AppNavItem(label: 'پروفایل', icon: Icons.person_outline, activeIcon: Icons.person),
        ],
      ),
    )));

    expect(tester.getCenter(find.text('خانه')).dx, greaterThan(tester.getCenter(find.text('پروفایل')).dx));
    await tester.tap(find.text('پروفایل'));
    expect(tapped, 1);
  });

  testWidgets('ErrorView shows the Persian message and a retry action, never raw exceptions', (tester) async {
    var retried = false;
    await tester.pumpWidget(testApp(Scaffold(body: ErrorView(error: ApiException.network, onRetry: () => retried = true))));

    expect(find.text(ApiException.network.message), findsOneWidget);
    await tester.tap(find.text('تلاش دوباره'));
    expect(retried, isTrue);

    await tester.pumpWidget(testApp(Scaffold(body: ErrorView(error: StateError('SocketException: boom')))));
    expect(find.textContaining('SocketException'), findsNothing);
    expect(find.text(ApiException.unknown.message), findsOneWidget);
  });
}
