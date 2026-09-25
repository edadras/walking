import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/features/sponsors/data/sponsor_models.dart';
import 'package:gamyar/features/sponsors/data/sponsor_repository.dart';
import 'package:gamyar/features/weather/data/weather.dart';
import 'package:gamyar/features/weather/presentation/weather_card.dart';
import 'package:gamyar/features/weather/presentation/weather_page.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/test_app.dart';

/// Exactly what the server returns for the recorded Open-Meteo responses.
final _json = jsonDecode(File('test/features/weather/weather_fixture.json').readAsStringSync())['data'] as Map<String, dynamic>;

class _Repo implements WeatherRepository {
  final calls = <(double, double)>[];

  @override
  Future<WeatherReport> at(double lat, double lng) async {
    calls.add((lat, lng));
    return WeatherReport.fromJson(_json);
  }
}

class _Sponsors extends Mock implements SponsorRepository {}

void main() {
  test('parses the server report', () {
    final w = WeatherReport.fromJson(_json);
    expect(w.now.tempC, 33.2);
    expect(w.now.windDir, 'جنوب');
    expect(w.air!.aqi, 93);
    expect(w.hourly, hasLength(12));
    expect(w.daily, hasLength(3));
    expect(w.advice.first.key, 'dry');
    expect(w.walkScore, 100);
    expect(w.sunset, DateTime.parse('2026-09-25T17:57:00+03:30'));
  });

  testWidgets('card shows conditions, the outdoor numbers, next hours and advice', (tester) async {
    final repo = _Repo();
    await tester.pumpWidget(testApp(
      const Scaffold(body: SingleChildScrollView(child: WeatherCard())),
      place: (lat: 35.7219, lng: 51.3347),
      overrides: [weatherRepositoryProvider.overrideWithValue(repo)],
    ));
    await tester.pumpAndSettle();

    expect(repo.calls, [(35.7219, 51.3347)]);
    expect(find.text('۳۳°'), findsWidgets);
    expect(find.text('صاف'), findsOneWidget);
    expect(find.text('احساس ۳۰°'), findsOneWidget);
    expect(find.text('۱۰٪'), findsOneWidget); // humidity
    expect(find.text('۸ km/h'), findsOneWidget);
    expect(find.text('۹۳'), findsOneWidget); // US AQI
    expect(find.text('عالی'), findsOneWidget); // walk index
    expect(find.textContaining('هوا خشک است'), findsOneWidget);
  });

  testWidgets('without location permission the card asks instead of guessing', (tester) async {
    await tester.pumpWidget(testApp(const Scaffold(body: WeatherCard()), overrides: [weatherRepositoryProvider.overrideWithValue(_Repo())]));
    await tester.pumpAndSettle();
    expect(find.textContaining('اجازه موقعیت مکانی'), findsOneWidget);
    expect(find.text('۳۳°'), findsNothing);
  });

  testWidgets('detail page: advice, 3 days, sun times, air and places around', (tester) async {
    tester.view.physicalSize = const Size(1170, 4000);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    final sponsors = _Sponsors();
    when(() => sponsors.nearby(any(), any(), radiusKm: any(named: 'radiusKm'))).thenAnswer((_) async => [
          NearbyPlace.fromJson({
            'id': 'l1', 'name': 'شعبه ونک', 'lat': 35.75, 'lng': 51.41, 'radius_m': 80, 'distance_m': 420,
            'sponsor': {'id': 's1', 'name': 'کافه راه'},
            'campaigns': [{'id': 'c1', 'name': 'قهوه', 'reward_points': 30, 'ends_at': '2026-12-01T00:00:00Z', 'eligible': true}],
          }),
        ]);
    await tester.pumpWidget(testApp(
      const WeatherPage(),
      place: (lat: 35.7, lng: 51.4),
      overrides: [weatherRepositoryProvider.overrideWithValue(_Repo()), sponsorRepositoryProvider.overrideWithValue(sponsors)],
    ));
    await tester.pumpAndSettle();

    expect(find.text('توصیه‌ها برای پیاده‌روی'), findsOneWidget);
    expect(find.text('امروز'), findsOneWidget);
    expect(find.text('طلوع'), findsOneWidget);
    expect(find.text('۹۳ · قابل قبول'), findsOneWidget);
    expect(find.text('۱٬۱۹۹ متر'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('کافه راه'), 300, scrollable: find.byType(Scrollable).first);
    expect(find.text('کافه راه'), findsOneWidget);
    expect(find.text('شعبه ونک · ۴۲۰ متر'), findsOneWidget);
    verify(() => sponsors.nearby(35.7, 51.4, radiusKm: 2)).called(1);
  });

  test('coordinates leave the phone rounded to ~1 km', () async {
    final api = _Api();
    await WeatherRepository(api).at(35.72191, 51.33476);
    expect(api.query, {'lat': '35.72', 'lng': '51.33'});
  });
}

class _Api extends Fake implements ApiClient {
  Map<String, dynamic>? query;

  @override
  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query, Object? options}) async {
    this.query = query;
    return {'data': _json};
  }
}
