import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/state_views.dart';
import '../../sponsors/data/sponsor_models.dart';
import '../../sponsors/data/sponsor_repository.dart';
import '../application/weather_providers.dart';
import '../data/weather.dart';
import 'weather_card.dart';
import 'weather_visuals.dart';

/// Reward places within walking distance of the weather fix.
final aroundYouProvider = FutureProvider.autoDispose<List<NearbyPlace>>((ref) async {
  final place = await ref.watch(coarseLocationProvider).current();
  return ref.watch(sponsorRepositoryProvider).nearby(place.lat, place.lng, radiusKm: 2);
});

class WeatherPage extends ConsumerWidget {
  const WeatherPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final weather = ref.watch(weatherProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.weatherTitle)),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(weatherProvider);
          ref.invalidate(aroundYouProvider);
          await ref.read(weatherProvider.future);
        },
        child: ListView(
          padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter, vertical: AppSpacing.lg),
          children: [
            const WeatherCard(hourCount: 0, onTap: _noop),
            ...switch (weather) {
              AsyncData(:final value) => _details(context, value),
              _ => const <Widget>[],
            },
            SectionHeader(title: l.weatherAround),
            const _AroundYou(),
            const SizedBox(height: AppSpacing.lg),
            Center(child: Text(l.weatherSource, style: context.text.labelSmall)),
          ],
        ),
      ),
    );
  }

  static void _noop() {}

  List<Widget> _details(BuildContext context, WeatherReport w) {
    final l = context.l10n;
    final p = context.palette;
    return [
      SectionHeader(title: l.weatherAdvice),
      AppCard(
        child: Column(children: [
          for (final a in w.advice)
            Padding(
              padding: const EdgeInsetsDirectional.symmetric(vertical: AppSpacing.xs),
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Padding(
                  padding: const EdgeInsetsDirectional.only(top: 6),
                  child: Container(width: 8, height: 8, decoration: BoxDecoration(color: WeatherVisuals.levelColor(a.level), shape: BoxShape.circle)),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(child: Text(a.text, style: context.text.bodyMedium)),
              ]),
            ),
        ]),
      ),
      SectionHeader(title: l.weatherHourly),
      AppCard(
        padding: const EdgeInsetsDirectional.symmetric(vertical: AppSpacing.md),
        child: SizedBox(
          height: 96,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.md),
            itemCount: w.hourly.length,
            separatorBuilder: (_, _) => const SizedBox(width: AppSpacing.lg),
            itemBuilder: (context, i) {
              final h = w.hourly[i];
              return Column(children: [
                Text(i == 0 ? l.weatherNow : Fa.digits(h.time.toLocal().hour), style: context.text.labelSmall),
                const SizedBox(height: AppSpacing.xs),
                Icon(WeatherVisuals.icon(h.icon), size: 22, color: p.info),
                const SizedBox(height: AppSpacing.xs),
                Text(temp(h.tempC), style: context.text.labelLarge),
                Text(h.precipProb > 0 ? Fa.percent(h.precipProb) : '—', style: context.text.labelSmall?.copyWith(color: p.inkSubtle, fontSize: 10)),
              ]);
            },
          ),
        ),
      ),
      SectionHeader(title: l.weatherDaily),
      AppCard(
        child: Column(children: [
          for (final (i, d) in w.daily.indexed)
            Padding(
              padding: const EdgeInsetsDirectional.symmetric(vertical: AppSpacing.xs),
              child: Row(children: [
                SizedBox(width: 72, child: Text(i == 0 ? l.weatherToday : FaDate.weekday(d.date), style: context.text.bodyMedium)),
                Icon(WeatherVisuals.icon(d.icon), size: 20, color: p.info),
                const SizedBox(width: AppSpacing.sm),
                Expanded(child: Text(d.condition, style: context.text.bodySmall)),
                if (d.precipProb > 0) Text('☂ ${Fa.percent(d.precipProb)}  ', style: context.text.labelSmall),
                Text('${temp(d.maxC)} / ${temp(d.minC)}', style: context.text.labelLarge),
              ]),
            ),
        ]),
      ),
      SectionHeader(title: l.weatherDetails),
      AppCard(
        child: Wrap(runSpacing: AppSpacing.md, children: [
          _Detail(icon: Icons.wb_twilight_rounded, label: l.weatherSunrise, value: w.sunrise == null ? '—' : FaDate.time(w.sunrise!)),
          _Detail(icon: Icons.nights_stay_outlined, label: l.weatherSunset, value: w.sunset == null ? '—' : FaDate.time(w.sunset!)),
          _Detail(icon: Icons.wb_sunny_outlined, label: l.weatherUv, value: '${Fa.decimal(w.now.uv)} · ${WeatherVisuals.uvLabel(context, w.now.uvLevel)}'),
          _Detail(icon: Icons.air_rounded, label: l.weatherGusts, value: '${Fa.number(w.now.gustsKmh.round())} km/h · ${w.now.windDir}'),
          if (w.air != null) _Detail(icon: Icons.masks_outlined, label: l.weatherAir, value: '${Fa.number(w.air!.aqi)} · ${w.air!.label}'),
          if (w.air?.pm25 != null) _Detail(icon: Icons.blur_on_rounded, label: 'PM2.5', value: '${Fa.decimal(w.air!.pm25!)} µg/m³'),
          if (w.elevationM != null) _Detail(icon: Icons.terrain_rounded, label: l.weatherElevation, value: l.weatherMeters(Fa.number(w.elevationM!))),
          _Detail(icon: Icons.update_rounded, label: l.weatherUpdatedLabel, value: FaDate.time(w.observedAt)),
        ]),
      ),
    ];
  }
}

class _Detail extends StatelessWidget {
  const _Detail({required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => FractionallySizedBox(
        widthFactor: 0.5,
        child: Row(children: [
          Icon(icon, size: 20, color: context.palette.inkMuted),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(label, style: context.text.labelSmall),
              Text(value, style: context.text.bodyMedium, maxLines: 1, overflow: TextOverflow.ellipsis),
            ]),
          ),
        ]),
      );
}

class _AroundYou extends ConsumerWidget {
  const _AroundYou();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    return AsyncView(
      value: ref.watch(aroundYouProvider),
      onRetry: () => ref.invalidate(aroundYouProvider),
      data: (places) => places.isEmpty
          ? AppCard(child: Text(l.weatherAroundEmpty, style: context.text.bodySmall))
          : AppCard(
              padding: EdgeInsetsDirectional.zero,
              child: Column(children: [
                for (final place in places.take(5))
                  ListTile(
                    leading: Icon(Icons.storefront_outlined, color: p.green),
                    title: Text(place.sponsor.name),
                    subtitle: Text([place.branch.name, if (place.branch.distanceM != null) l.weatherMeters(Fa.number(place.branch.distanceM!))].join(' · ')),
                    trailing: place.bestPoints > 0 ? Text('+${Fa.number(place.bestPoints)}', style: context.text.labelLarge?.copyWith(color: p.goldInk)) : null,
                    onTap: () => context.push('/nearby'),
                  ),
              ]),
            ),
    );
  }
}
