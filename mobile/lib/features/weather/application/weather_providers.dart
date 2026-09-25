import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

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

/// Called with every fresh report (the home-screen widget listens to keep its copy current).
final weatherSinkProvider = Provider<void Function(WeatherReport)>((ref) => (_) {});

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
