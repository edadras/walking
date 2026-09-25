import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/config/env.dart';
import '../../../core/providers.dart';
import '../../../core/storage/secure_store.dart';
import '../../config/data/app_config.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/permissions/permission_primer.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/stat_tile.dart';
import '../../../core/widgets/state_views.dart';
import '../application/location_source.dart';
import '../data/sponsor_models.dart';
import 'sponsor_widgets.dart';
import '../../../core/widgets/net_image.dart';

/// «جایزه‌های اطراف من» — list and map of sponsor branches with running offers.
class NearbyPage extends ConsumerStatefulWidget {
  const NearbyPage({super.key});

  @override
  ConsumerState<NearbyPage> createState() => _NearbyPageState();
}

class _NearbyPageState extends ConsumerState<NearbyPage> {
  bool _map = false;

  Future<void> _askPermission() async {
    final ok = await PermissionPrimer.ensure(context, AppPermission.location);
    if (!mounted) return;
    ref.invalidate(locationPermissionProvider);
    if (ok) ref.invalidate(nearbyProvider);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(
        title: Text(l.nearbyTitle),
        actions: [
          IconButton(tooltip: l.couponsTitle, icon: const Icon(Icons.confirmation_number_outlined), onPressed: () => context.push('/coupons')),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(52),
          child: Padding(
            padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, 0, AppSpacing.gutter, AppSpacing.sm),
            child: SegmentedButton<bool>(
              showSelectedIcon: false,
              segments: [ButtonSegment(value: false, label: Text(l.nearbyList)), ButtonSegment(value: true, label: Text(l.nearbyMap))],
              selected: {_map},
              onSelectionChanged: (s) => setState(() => _map = s.first),
            ),
          ),
        ),
      ),
      body: switch (ref.watch(locationPermissionProvider).value) {
        null => const LoadingView(),
        false => _LocationPrompt(message: l.nearbyLocationDenied, onPressed: _askPermission),
        true => switch (ref.watch(nearbyProvider)) {
            AsyncError(error: LocationUnavailable(problem: LocationProblem.serviceOff)) => _LocationPrompt(
                message: l.nearbyLocationOff,
                onPressed: () async {
                  await openLocationSettings();
                  ref.invalidate(nearbyProvider);
                },
              ),
            AsyncError(error: LocationUnavailable()) => _LocationPrompt(message: l.nearbyLocationDenied, onPressed: _askPermission),
            final value => AsyncView(
                value: value,
                onRetry: () => ref.invalidate(nearbyProvider),
                data: (r) => _map ? _PlacesMap(center: LatLng(r.fix.lat, r.fix.lng), places: r.places) : _PlacesList(places: r.places),
              ),
          },
      },
    );
  }
}

class _LocationPrompt extends StatelessWidget {
  const _LocationPrompt({required this.message, required this.onPressed});

  final String message;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsetsDirectional.all(AppSpacing.xxl),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          Icon(Icons.place_outlined, size: 48, color: context.palette.green),
          const SizedBox(height: AppSpacing.lg),
          Text(message, textAlign: TextAlign.center, style: context.text.bodyLarge),
          const SizedBox(height: AppSpacing.sm),
          Text(context.l10n.nearbyPrivacy, textAlign: TextAlign.center, style: context.text.bodySmall),
          const SizedBox(height: AppSpacing.xl),
          AppButton(label: context.l10n.nearbyEnableLocation, expand: false, onPressed: onPressed),
        ]),
      );
}

class _PlacesList extends ConsumerWidget {
  const _PlacesList({required this.places});

  final List<NearbyPlace> places;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return RefreshIndicator(
      color: context.palette.green,
      onRefresh: () async => ref.invalidate(nearbyProvider),
      child: ListView(
        padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
        children: [
          if (places.isEmpty) EmptyView(title: l.nearbyEmpty) else for (final p in places) Padding(padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.md), child: PlaceCard(place: p)),
          const SizedBox(height: AppSpacing.sm),
          Text(l.nearbyPrivacy, style: context.text.bodySmall?.copyWith(color: context.palette.inkSubtle), textAlign: TextAlign.center),
        ],
      ),
    );
  }
}

class PlaceCard extends StatelessWidget {
  const PlaceCard({super.key, required this.place});

  final NearbyPlace place;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final b = place.branch;
    return AppCard(
      padding: EdgeInsets.zero,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Padding(
          padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.lg, AppSpacing.lg, AppSpacing.lg, AppSpacing.sm),
          child: Row(children: [
            NetAvatar(
              backgroundColor: p.greenSoft,
              url: place.sponsor.logoUrl,
              child: Icon(Icons.storefront_outlined, color: p.green),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(place.sponsor.name, style: context.text.titleSmall),
                Text([b.name, distanceLabel(context, b.distanceM), if (!b.openNow) l.branchClosed].where((s) => s.isNotEmpty).join(' · '), style: context.text.bodySmall),
              ]),
            ),
          ]),
        ),
        for (final c in place.campaigns)
          ListTile(
            onTap: () => context.push('/campaigns/${c.id}'),
            title: Text(c.name, style: context.text.bodyLarge),
            subtitle: Text(
              [l.campaignStay(Fa.digits((c.minStaySeconds / 60).ceil())), if (c.requiresQr) l.campaignQr, if (!c.eligible) reasonLabel(context, c.ineligibleReason)].join(' · '),
              style: context.text.bodySmall?.copyWith(color: c.eligible ? null : p.danger),
            ),
            trailing: Row(mainAxisSize: MainAxisSize.min, children: [
              if (c.rewardPoints > 0) PointsChip(label: '+${Fa.number(c.rewardPoints)}'),
              if (c.coupon != null) Padding(padding: const EdgeInsetsDirectional.only(start: AppSpacing.xs), child: Icon(Icons.confirmation_number_outlined, color: p.goldInk, size: 20)),
            ]),
          ),
      ]),
    );
  }
}

/// Proxied tiles are fetched with the session token (the provider's key stays on our server).
final _tileHeadersProvider = FutureProvider.autoDispose<Map<String, String>>((ref) async {
  if (!ref.watch(configProvider).mapProxied) return const {};
  final token = await ref.watch(secureStoreProvider).read(SecureStore.kToken);
  return token == null ? const {} : {'Authorization': 'Bearer $token'};
});

class _PlacesMap extends ConsumerWidget {
  const _PlacesMap({required this.center, required this.places});

  final LatLng center;
  final List<NearbyPlace> places;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    final config = ref.watch(configProvider);
    final headers = ref.watch(_tileHeadersProvider).value ?? const {};
    return FlutterMap(
      options: MapOptions(initialCenter: center, initialZoom: 14, maxZoom: config.mapMaxZoom),
      children: [
        TileLayer(
          urlTemplate: config.mapTileUrl ?? Env.mapTileUrl,
          maxZoom: config.mapMaxZoom,
          userAgentPackageName: 'ir.gamyar.app',
          tileProvider: NetworkTileProvider(headers: headers),
        ),
        MarkerLayer(markers: [
          Marker(point: center, width: 18, height: 18, child: Container(decoration: BoxDecoration(color: p.info, shape: BoxShape.circle, border: Border.all(color: Colors.white, width: 3)))),
          for (final place in places)
            Marker(
              point: LatLng(place.branch.lat, place.branch.lng),
              width: 64,
              height: 40,
              child: GestureDetector(
                onTap: () => showModalBottomSheet<void>(
                  context: context,
                  showDragHandle: true,
                  builder: (c) => Padding(padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, 0, AppSpacing.gutter, AppSpacing.xxl), child: PlaceCard(place: place)),
                ),
                child: Container(
                  alignment: Alignment.center,
                  decoration: BoxDecoration(color: p.green, borderRadius: BorderRadius.circular(20), boxShadow: const [BoxShadow(blurRadius: 6, color: Color(0x33000000))]),
                  child: Text('+${Fa.number(place.bestPoints)}', style: context.text.labelMedium?.copyWith(color: Colors.white, fontWeight: FontWeight.w700)),
                ),
              ),
            ),
        ]),
        RichAttributionWidget(attributions: [TextSourceAttribution(config.mapAttribution)]),
      ],
    );
  }
}

/// Opens system location settings (GPS off).
Future<void> openLocationSettings() => Geolocator.openLocationSettings();
