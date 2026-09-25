import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/permissions/permission_primer.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/skeleton.dart';
import '../../../core/widgets/state_views.dart';
import '../../sponsors/application/location_source.dart';
import '../application/weather_providers.dart';
import '../data/weather.dart';
import 'weather_visuals.dart';

String temp(double c) => '${Fa.number(c.round())}°';

/// Current weather for the walk: sky-coloured card with conditions, the four numbers
/// that matter outdoors (humidity, wind, UV, air), the next hours and the top advice.
class WeatherCard extends ConsumerWidget {
  const WeatherCard({super.key, this.hourCount = 6, this.onTap});

  final int hourCount;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final weather = ref.watch(weatherProvider);
    return weather.when(
      skipLoadingOnRefresh: true,
      data: (w) => WeatherPanel(report: w, hourCount: hourCount, onTap: onTap ?? () => context.push('/weather')),
      loading: () => const Shimmer(child: SkeletonBox(height: 188, radius: AppRadius.md)),
      error: (e, _) => _WeatherUnavailable(error: e),
    );
  }
}

class WeatherPanel extends StatelessWidget {
  const WeatherPanel({super.key, required this.report, this.hourCount = 6, this.onTap});

  final WeatherReport report;
  final int hourCount;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final w = report.now;
    final today = report.daily.isEmpty ? null : report.daily.first;
    final top = report.advice.isEmpty ? null : report.advice.first;
    const white = Colors.white;
    final dim = white.withValues(alpha: 0.78);

    return Semantics(
      label: '${l.weatherTitle}: ${w.condition} ${temp(w.tempC)}',
      child: Material(
        borderRadius: AppRadius.mdAll,
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Ink(
            decoration: BoxDecoration(
              gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: WeatherVisuals.sky(w.icon, w.isDay)),
            ),
            padding: const EdgeInsetsDirectional.all(AppSpacing.lg),
            child: DefaultTextStyle.merge(
              style: const TextStyle(color: white),
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Icon(WeatherVisuals.icon(w.icon), color: white, size: 44),
                  const SizedBox(width: AppSpacing.md),
                  Text(temp(w.tempC), style: context.text.displaySmall?.copyWith(color: white, fontWeight: FontWeight.w300, height: 1)),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(w.condition, style: context.text.titleMedium?.copyWith(color: white)),
                      Text(l.weatherFeelsLike(temp(w.feelsLikeC)), style: context.text.bodySmall?.copyWith(color: dim)),
                      if (today != null) Text('${temp(today.maxC)} / ${temp(today.minC)}', style: context.text.bodySmall?.copyWith(color: dim)),
                    ]),
                  ),
                  _WalkIndex(score: report.walkScore, label: report.walkLabel),
                ]),
                const SizedBox(height: AppSpacing.md),
                Row(children: [
                  Expanded(child: _Metric(icon: Icons.water_drop_outlined, label: l.weatherHumidity, value: Fa.percent(w.humidity))),
                  Expanded(
                    child: _Metric(
                      icon: Icons.air_rounded,
                      label: l.weatherWind,
                      value: '${Fa.number(w.windKmh.round())} km/h',
                      trailing: Transform.rotate(angle: (w.windDirDeg + 180) * math.pi / 180, child: const Icon(Icons.navigation_rounded, size: 12, color: white)),
                    ),
                  ),
                  Expanded(
                    child: _Metric(icon: Icons.wb_sunny_outlined, label: l.weatherUv, value: Fa.decimal(w.uv), dot: WeatherVisuals.uvColor(w.uvLevel)),
                  ),
                  if (report.air != null)
                    Expanded(
                      child: _Metric(icon: Icons.masks_outlined, label: l.weatherAir, value: Fa.number(report.air!.aqi), dot: WeatherVisuals.aqiColor(report.air!.level)),
                    ),
                ]),
                if (hourCount > 0 && report.hourly.length > 1) ...[
                  const SizedBox(height: AppSpacing.md),
                  Divider(height: 1, color: white.withValues(alpha: 0.2)),
                  const SizedBox(height: AppSpacing.sm),
                  Row(children: [
                    for (final h in report.hourly.skip(1).take(hourCount))
                      Expanded(
                        child: Column(children: [
                          Text(Fa.digits(h.time.toLocal().hour), style: context.text.labelSmall?.copyWith(color: dim)),
                          const SizedBox(height: AppSpacing.xxs),
                          Icon(WeatherVisuals.icon(h.icon), size: 18, color: white),
                          const SizedBox(height: AppSpacing.xxs),
                          Text(temp(h.tempC), style: context.text.labelMedium?.copyWith(color: white)),
                          if (h.precipProb >= 20) Text(Fa.percent(h.precipProb), style: context.text.labelSmall?.copyWith(color: const Color(0xFFBFE3FF), fontSize: 10)),
                        ]),
                      ),
                  ]),
                ],
                if (top != null) ...[
                  const SizedBox(height: AppSpacing.md),
                  Container(
                    padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.md, vertical: AppSpacing.sm),
                    decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.18), borderRadius: AppRadius.smAll),
                    child: Row(children: [
                      Container(width: 8, height: 8, decoration: BoxDecoration(color: WeatherVisuals.levelColor(top.level), shape: BoxShape.circle)),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(child: Text(top.text, style: context.text.bodySmall?.copyWith(color: white))),
                    ]),
                  ),
                ],
                if (report.stale) ...[
                  const SizedBox(height: AppSpacing.xs),
                  Text('${l.weatherUpdated(FaDate.time(report.observedAt))} ${l.weatherStale}', style: context.text.labelSmall?.copyWith(color: dim)),
                ],
              ]),
            ),
          ),
        ),
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.icon, required this.label, required this.value, this.dot, this.trailing});

  final IconData icon;
  final String label;
  final String value;
  final Color? dot;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final dim = Colors.white.withValues(alpha: 0.78);
    return Column(children: [
      Icon(icon, size: 18, color: dim),
      const SizedBox(height: AppSpacing.xxs),
      Row(mainAxisSize: MainAxisSize.min, children: [
        if (dot != null) ...[Container(width: 7, height: 7, decoration: BoxDecoration(color: dot, shape: BoxShape.circle)), const SizedBox(width: 4)],
        Flexible(child: Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: context.text.labelLarge?.copyWith(color: Colors.white))),
        if (trailing != null) ...[const SizedBox(width: 2), trailing!],
      ]),
      Text(label, style: context.text.labelSmall?.copyWith(color: dim, fontSize: 10)),
    ]);
  }
}

class _WalkIndex extends StatelessWidget {
  const _WalkIndex({required this.score, required this.label});

  final int score;
  final String label;

  @override
  Widget build(BuildContext context) {
    final color = score >= 80 ? const Color(0xFF6EE7A8) : (score >= 60 ? const Color(0xFFFDE68A) : (score >= 40 ? const Color(0xFFFDBA74) : const Color(0xFFFCA5A5)));
    return SizedBox(
      width: 58,
      height: 58,
      child: Stack(alignment: Alignment.center, children: [
        SizedBox.expand(
          child: CircularProgressIndicator(value: score / 100, strokeWidth: 4, color: color, backgroundColor: Colors.white.withValues(alpha: 0.2)),
        ),
        Column(mainAxisSize: MainAxisSize.min, children: [
          Text(Fa.number(score), style: context.text.labelLarge?.copyWith(color: Colors.white, height: 1.1)),
          Text(label, style: context.text.labelSmall?.copyWith(color: Colors.white, fontSize: 9, height: 1.1)),
        ]),
      ]),
    );
  }
}

class _WeatherUnavailable extends ConsumerWidget {
  const _WeatherUnavailable({required this.error});

  final Object error;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    final location = error is LocationUnavailable ? (error as LocationUnavailable).problem : null;
    return AppCard(
      child: Row(children: [
        Icon(Icons.wb_cloudy_outlined, color: p.inkSubtle),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Text(
            switch (location) {
              LocationProblem.denied => l.weatherEnableLocation,
              LocationProblem.serviceOff => l.weatherLocationOff,
              null => ErrorView.messageOf(error),
            },
            style: context.text.bodySmall,
          ),
        ),
        TextButton(
          onPressed: () async {
            if (location == LocationProblem.denied && !await PermissionPrimer.ensure(context, AppPermission.location)) return;
            ref.invalidate(weatherProvider);
          },
          child: Text(location == LocationProblem.denied ? l.weatherEnableAction : l.commonRetry),
        ),
      ]),
    );
  }
}

/// One-line conditions for the live walk screen; hidden until weather is known.
class WeatherStrip extends ConsumerWidget {
  const WeatherStrip({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final w = ref.watch(weatherProvider).value;
    if (w == null) return const SizedBox.shrink();
    final l = context.l10n;
    final p = context.palette;
    final top = w.advice.isEmpty ? null : w.advice.first;
    final style = context.text.labelMedium;
    return AppCard(
      padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.md, vertical: AppSpacing.sm),
      onTap: () => context.push('/weather'),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Icon(WeatherVisuals.icon(w.now.icon), size: 20, color: p.info),
          const SizedBox(width: AppSpacing.xs),
          Text(temp(w.now.tempC), style: context.text.titleSmall),
          const SizedBox(width: AppSpacing.md),
          Icon(Icons.water_drop_outlined, size: 14, color: p.inkMuted),
          Text(' ${Fa.percent(w.now.humidity)}', style: style),
          const SizedBox(width: AppSpacing.md),
          Icon(Icons.air_rounded, size: 14, color: p.inkMuted),
          Text(' ${Fa.number(w.now.windKmh.round())}', style: style),
          const SizedBox(width: AppSpacing.md),
          Text('${l.weatherUv} ', style: style),
          Text(Fa.decimal(w.now.uv), style: style?.copyWith(color: WeatherVisuals.uvColor(w.now.uvLevel))),
          if (w.air != null) ...[
            const SizedBox(width: AppSpacing.md),
            Icon(Icons.masks_outlined, size: 14, color: WeatherVisuals.aqiColor(w.air!.level)),
            Text(' ${Fa.number(w.air!.aqi)}', style: style),
          ],
        ]),
        if (top != null && top.level != 'good') ...[
          const SizedBox(height: AppSpacing.xxs),
          Text(top.text, style: context.text.bodySmall?.copyWith(color: WeatherVisuals.levelColor(top.level)), maxLines: 2),
        ],
      ]),
    );
  }
}
