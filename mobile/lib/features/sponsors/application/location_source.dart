import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

import '../../../core/permissions/permission_primer.dart';
import '../data/sponsor_models.dart';
import '../data/sponsor_repository.dart';

enum LocationProblem { serviceOff, denied }

class LocationUnavailable implements Exception {
  const LocationUnavailable(this.problem);

  final LocationProblem problem;
}

/// Foreground-only location for nearby rewards and visits. Never used in the
/// background and nothing is stored on the device.
abstract class LocationSource {
  Future<Fix> current();
}

class GeolocatorSource implements LocationSource {
  @override
  Future<Fix> current() async {
    if (!await Geolocator.isLocationServiceEnabled()) throw const LocationUnavailable(LocationProblem.serviceOff);
    final permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
      throw const LocationUnavailable(LocationProblem.denied);
    }
    final p = await Geolocator.getCurrentPosition(
      locationSettings: AndroidSettings(accuracy: LocationAccuracy.high, timeLimit: const Duration(seconds: 20), forceLocationManager: false),
    );
    return (lat: p.latitude, lng: p.longitude, accuracy: p.accuracy, mock: p.isMocked);
  }
}

/// Whether foreground location is already granted (overridable in tests).
final locationPermissionProvider = FutureProvider.autoDispose<bool>((ref) => PermissionPrimer.isGranted(AppPermission.location));

final locationSourceProvider = Provider<LocationSource>((ref) => GeolocatorSource());

/// Last known fix while the nearby page is open (refreshed on pull).
final nearbyProvider = FutureProvider.autoDispose<({Fix fix, List<NearbyPlace> places})>((ref) async {
  final fix = await ref.watch(locationSourceProvider).current();
  final places = await ref.watch(sponsorRepositoryProvider).nearby(fix.lat, fix.lng);
  return (fix: fix, places: places);
});
