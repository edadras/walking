import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/config/env.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../auth/application/session_controller.dart';
import '../../config/data/app_config.dart';
import '../../profile/data/profile_repository.dart';
import '../../sponsors/presentation/nearby_page.dart' show tileHeadersProvider;
import '../../weather/application/weather_providers.dart';
import '../data/route_map.dart';

/// Everyone's walks of the last 24 hours, one colour per person, no names.
class RouteMapPage extends ConsumerStatefulWidget {
  const RouteMapPage({super.key});

  @override
  ConsumerState<RouteMapPage> createState() => _RouteMapPageState();
}

class _RouteMapPageState extends ConsumerState<RouteMapPage> {
  static const _tehran = LatLng(35.7, 51.39);
  final _map = MapController();
  List<MapTrack> _tracks = const [];
  bool _tooWide = false;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    // Start where the user is, when we may know it; otherwise Tehran.
    ref.read(coarseLocationProvider).current().then((p) {
      if (mounted) _map.move(LatLng(p.lat, p.lng), 14);
    }).catchError((_) {});
  }

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  void _onMove(MapEvent event) {
    if (event is MapEventMoveEnd || event is MapEventFlingAnimationEnd || event is MapEventDoubleTapZoomEnd || event is MapEventScrollWheelZoom) {
      _debounce?.cancel();
      _debounce = Timer(const Duration(milliseconds: 300), _load);
    }
  }

  Future<void> _load() async {
    final b = _map.camera.visibleBounds;
    if (b.north - b.south > 5 || b.east - b.west > 5) {
      setState(() => _tooWide = true);
      return;
    }
    try {
      final tracks = await ref.read(routeMapRepositoryProvider).tracks(south: b.south, west: b.west, north: b.north, east: b.east);
      if (mounted) {
        setState(() {
          _tracks = tracks;
          _tooWide = false;
        });
      }
    } on ApiException {
      // Keep what is on screen.
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final config = ref.watch(configProvider);
    final headers = ref.watch(tileHeadersProvider).value ?? const {};
    return Scaffold(
      appBar: AppBar(title: Text(l.routeMapTitle)),
      body: Stack(children: [
        FlutterMap(
          mapController: _map,
          options: MapOptions(initialCenter: _tehran, initialZoom: 12, maxZoom: config.mapMaxZoom, onMapEvent: _onMove, onMapReady: _load),
          children: [
            TileLayer(
              urlTemplate: config.mapTileUrl ?? Env.mapTileUrl,
              maxZoom: config.mapMaxZoom,
              userAgentPackageName: 'ir.gamyar.app',
              tileProvider: NetworkTileProvider(headers: headers),
            ),
            PolylineLayer(polylines: [
              for (final t in _tracks)
                Polyline(
                  points: t.points,
                  strokeWidth: 4,
                  // Lines fade as their 24 hours run down.
                  color: hexColor(t.color).withValues(alpha: 0.35 + 0.6 * (t.expiresIn.inSeconds / 86400).clamp(0, 1)),
                  strokeCap: StrokeCap.round,
                  strokeJoin: StrokeJoin.round,
                ),
            ]),
            RichAttributionWidget(attributions: [TextSourceAttribution(config.mapAttribution)]),
          ],
        ),
        PositionedDirectional(
          top: AppSpacing.md,
          start: AppSpacing.md,
          end: AppSpacing.md,
          child: Material(
            color: p.surface.withValues(alpha: 0.95),
            borderRadius: AppRadius.mdAll,
            elevation: 2,
            child: Padding(
              padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.md, vertical: AppSpacing.sm),
              child: Text(
                _tooWide ? l.routeMapZoomIn : l.routeMapCount(Fa.number(_tracks.length)),
                style: context.text.labelLarge,
              ),
            ),
          ),
        ),
        const PositionedDirectional(bottom: AppSpacing.lg, start: AppSpacing.md, end: AppSpacing.md, child: _ShareCard()),
      ]),
    );
  }
}

/// My consent and my colour.
class _ShareCard extends ConsumerStatefulWidget {
  const _ShareCard();

  @override
  ConsumerState<_ShareCard> createState() => _ShareCardState();
}

class _ShareCardState extends ConsumerState<_ShareCard> {
  bool _saving = false;

  Future<void> _update(Map<String, Object?> fields) async {
    setState(() => _saving = true);
    try {
      final me = await ref.read(profileRepositoryProvider).updateSettings(fields);
      ref.read(sessionProvider.notifier).updateMe(me);
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final me = ref.watch(meProvider);
    final hours = Fa.number((ref.watch(configProvider).settings['map.visible_hours'] as num?) ?? 24);
    final trim = Fa.number((ref.watch(configProvider).settings['map.privacy_trim_m'] as num?) ?? 150);
    return Material(
      color: p.surface,
      borderRadius: AppRadius.mdAll,
      elevation: 3,
      child: Padding(
        padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.lg, AppSpacing.sm, AppSpacing.sm, AppSpacing.md),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Expanded(child: Text(l.routeMapShare, style: context.text.titleSmall)),
            Switch(value: me.shareRoute, onChanged: _saving ? null : (v) => _update({'share_route': v})),
          ]),
          Text(l.routeMapShareHint(hours, trim), style: context.text.bodySmall?.copyWith(color: p.inkMuted)),
          if (me.shareRoute) ...[
            const SizedBox(height: AppSpacing.sm),
            Text(l.routeMapColor, style: context.text.labelMedium),
            const SizedBox(height: AppSpacing.xs),
            Wrap(spacing: AppSpacing.sm, runSpacing: AppSpacing.sm, children: [
              for (final c in routeColors)
                Semantics(
                  button: true,
                  selected: me.routeColor == c,
                  label: c,
                  child: InkWell(
                    customBorder: const CircleBorder(),
                    onTap: _saving ? null : () => _update({'route_color': c}),
                    child: Container(
                      width: 28,
                      height: 28,
                      decoration: BoxDecoration(
                        color: hexColor(c),
                        shape: BoxShape.circle,
                        border: Border.all(color: me.routeColor == c ? p.ink : Colors.transparent, width: 3),
                      ),
                    ),
                  ),
                ),
            ]),
          ],
        ]),
      ),
    );
  }
}
