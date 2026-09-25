import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';

/// Colours users can pick for their lines (same list as the server).
const routeColors = ['#E5484D', '#F76B15', '#FFB224', '#30A46C', '#12A594', '#0090FF', '#3E63DD', '#8E4EC6', '#D6409F', '#AB4ABA', '#46A758', '#E54666'];

Color hexColor(String hex) => Color(int.parse('FF${hex.substring(1)}', radix: 16));

class MapTrack {
  const MapTrack({required this.color, required this.points, required this.expiresIn});

  factory MapTrack.fromJson(Map<String, dynamic> j) => MapTrack(
        color: j['color'] as String,
        points: [for (final p in j['points'] as List) LatLng(((p as List)[0] as num).toDouble(), (p[1] as num).toDouble())],
        expiresIn: Duration(seconds: (j['expires_in'] as num?)?.toInt() ?? 0),
      );

  final String color;
  final List<LatLng> points;

  /// Time left before the line disappears (24 h after its last point).
  final Duration expiresIn;
}

class RouteMapRepository {
  RouteMapRepository(this._api);

  final ApiClient _api;

  Future<List<MapTrack>> tracks({required double south, required double west, required double north, required double east}) async {
    final bbox = [west, south, east, north].map((v) => v.toStringAsFixed(4)).join(',');
    final r = await _api.get('/public/map/tracks', query: {'bbox': bbox});
    return (r['data'] as List).map((e) => MapTrack.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// [[lat, lng, epoch ms], ...] of a finished walk. The server keeps it only with consent.
  Future<void> upload(List<List<num>> points) => _api.post('/routes', data: {'points': points});
}

final routeMapRepositoryProvider = Provider<RouteMapRepository>((ref) => RouteMapRepository(ref.watch(apiClientProvider)));
