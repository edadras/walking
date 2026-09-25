import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

import '../../../core/platform/home_widget.dart';
import '../../sponsors/application/location_source.dart';
import '../data/weather.dart';

typedef Place = ({double lat, double lng});

/// Coarse, foreground-only position for weather: a recent last-known fix when there is
/// one (no GPS spin-up), otherwise a low-accuracy fix. Never prompts by itself.
abstract class CoarseLocation {
  Future<Place> current();
}

class GeolocatorCoarseLocation implements CoarseLocation {
  @override
  Future<Place> current() async {
    if (!await Geolocator.isLocationServiceEnabled()) throw const LocationUnavailable(LocationProblem.serviceOff);
    final permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
      throw const LocationUnavailable(LocationProblem.denied);
    }
    final last = await Geolocator.getLastKnownPosition();
    if (last != null && DateTime.now().difference(last.timestamp) < const Duration(minutes: 30)) {
      return (lat: last.latitude, lng: last.longitude);
    }
    final p = await Geolocator.getCurrentPosition(
      locationSettings: AndroidSettings(accuracy: LocationAccuracy.low, timeLimit: const Duration(seconds: 15)),
    );
    return (lat: p.latitude, lng: p.longitude);
  }
}

final coarseLocationProvider = Provider<CoarseLocation>((ref) => GeolocatorCoarseLocation());

/// Called with every fresh report: the home-screen widgets keep a copy of what they show.
final weatherSinkProvider = Provider<void Function(WeatherReport)>((ref) => (r) => ref.read(homeWidgetProvider).updateWeather(widgetSummary(r)));

/// The few numbers the widgets draw (no coordinates).
Map<String, Object?> widgetSummary(WeatherReport r) {
  final today = r.daily.isEmpty ? null : r.daily.first;
  final top = r.advice.isEmpty ? null : r.advice.first;
  return {
    't': r.now.tempC,
    'feels': r.now.feelsLikeC,
    'hi': today?.maxC ?? r.now.tempC,
    'lo': today?.minC ?? r.now.tempC,
    'cond': r.now.condition,
    'icon': r.now.icon,
    'day': r.now.isDay,
    'hum': r.now.humidity,
    'wind': r.now.windKmh,
    'uv': r.now.uv,
    if (r.air != null) 'aqi': r.air!.aqi,
    'index': r.walkScore,
    'advice': top?.text ?? '',
    'at': (r.stale ? r.observedAt : DateTime.now()).millisecondsSinceEpoch,
    'hours': [
      for (final h in r.hourly.skip(1).take(5)) {'h': h.time.toLocal().hour, 'icon': h.icon, 't': h.tempC},
    ],
  };
}

/// Weather where the user is. Kept for 10 minutes so switching tabs doesn't refetch.
final weatherProvider = FutureProvider.autoDispose<WeatherReport>((ref) async {
  final link = ref.keepAlive();
  final timer = Timer(const Duration(minutes: 10), link.close);
  ref.onDispose(timer.cancel);

  final place = await ref.watch(coarseLocationProvider).current();
  final report = await ref.watch(weatherRepositoryProvider).at(place.lat, place.lng);
  ref.read(weatherSinkProvider)(report);
  return report;
});
