import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/bar_chart.dart';
import '../../../core/widgets/state_views.dart';
import '../../../core/widgets/stat_tile.dart';
import '../application/activity_providers.dart';
import '../data/activity_models.dart';

class SessionDetailPage extends ConsumerWidget {
  const SessionDetailPage({super.key, required this.id});

  final String id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.sessionTitle)),
      body: AsyncView(
        value: ref.watch(walkSessionProvider(id)),
        onRetry: () => ref.invalidate(walkSessionProvider(id)),
        data: (s) => _Body(session: s),
      ),
    );
  }
}

class _Body extends StatelessWidget {
  const _Body({required this.session});

  final WalkSession session;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final s = session;
    return ListView(
      padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
      children: [
        Row(children: [
          if (s.isRide) ...[Icon(Icons.pedal_bike_rounded, color: p.info, size: 20), const SizedBox(width: AppSpacing.xs)],
          Text(s.isRide ? l.activityCycling : (s.isActive ? l.activityActive : l.activityPassive), style: context.text.labelMedium),
        ]),
        const SizedBox(height: AppSpacing.xs),
        Text(s.isRide ? l.activityCyclingKm(Fa.decimal(s.cyclingDistanceM / 1000)) : '${Fa.number(s.steps)} ${l.activitySteps}', style: context.text.displaySmall),
        if (s.isRide && s.steps > 0) Text('${Fa.number(s.steps)} ${l.activitySteps}', style: context.text.bodySmall),
        Text('${FaDate.long(s.startedAt)} · ${FaDate.time(s.startedAt)} تا ${FaDate.time(s.endedAt)}', style: context.text.bodySmall),
        const SizedBox(height: AppSpacing.xl),
        AppCard(
          child: Row(children: [
            Expanded(child: StatTile(value: FaDate.clock(Duration(seconds: s.durationS)), unit: '', label: l.sessionDuration)),
            Expanded(child: StatTile(value: Fa.decimal(s.distanceM / 1000), unit: l.homeUnitKm, label: l.homeDistance)),
            Expanded(child: StatTile(value: Fa.number(s.caloriesKcal.round()), unit: l.homeUnitKcal, label: l.homeCalories)),
          ]),
        ),
        const SizedBox(height: AppSpacing.md),
        AppCard(
          child: Row(children: [
            Icon(Icons.verified_outlined, color: s.status == 'verified' ? p.green : p.inkSubtle, size: 20),
            const SizedBox(width: AppSpacing.sm),
            Expanded(child: Text(l.sessionVerified, style: context.text.bodyMedium)),
            Text(s.verifiedSteps == null ? s.statusLabel : Fa.number(s.verifiedSteps!), style: context.text.titleSmall),
          ]),
        ),
        if (s.confidenceScore != null) ...[
          const SizedBox(height: AppSpacing.sm),
          AppCard(
            child: Row(children: [
              Icon(Icons.insights_rounded, color: p.inkSubtle, size: 20),
              const SizedBox(width: AppSpacing.sm),
              Expanded(child: Text(l.sessionConfidence, style: context.text.bodyMedium)),
              Text(Fa.percent(s.confidenceScore!), style: context.text.titleSmall),
            ]),
          ),
        ],
        if (s.isActive) ...[
          SectionHeader(title: l.sessionPerMinute),
          if (s.samples.isEmpty)
            Text(l.sessionSamplesExpired, style: context.text.bodySmall)
          else
            AppCard(
              child: MiniBarChart(
                values: [for (final x in s.samples) x.steps],
                height: 110,
                labels: [for (var i = 0; i < s.samples.length; i++) i % 10 == 0 ? Fa.digits(i) : null],
                semanticLabel: l.sessionPerMinute,
              ),
            ),
        ],
      ],
    );
  }
}
