import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/sensors/step_platform.dart';
import 'package:gamyar/features/activity/presentation/walk_page.dart';

import '../../helpers/test_app.dart';

void main() {
  test('live map from the recorder carries mode, ride distance and speed', () {
    final live = LiveWalk.fromMap({'steps': 40, 'elapsed_s': 600, 'distance_m': 3100.0, 'gps': true, 'mode': 'cycling', 'cycling_distance_m': 2900.0, 'speed_kmh': 18.4});
    expect(live.mode, MoveMode.cycling);
    expect(live.cyclingDistanceM, 2900);
    expect(live.speedKmh, 18.4);
    // Older native builds without the fields still parse.
    expect(LiveWalk.fromMap({'steps': 1, 'elapsed_s': 1}).mode, MoveMode.still);
  });

  testWidgets('a ride shows a bicycle, a walk shows a walker', (tester) async {
    await tester.pumpWidget(testApp(const Scaffold(body: Center(child: MoveModeBadge(mode: MoveMode.cycling)))));
    expect(find.byIcon(Icons.pedal_bike_rounded), findsOneWidget);
    expect(find.text('دوچرخه‌سواری'), findsOneWidget);

    await tester.pumpWidget(testApp(const Scaffold(body: Center(child: MoveModeBadge(mode: MoveMode.walking)))));
    await tester.pumpAndSettle();
    expect(find.byIcon(Icons.directions_walk_rounded), findsOneWidget);
    expect(find.text('پیاده‌روی'), findsOneWidget);
  });
}
