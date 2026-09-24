import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/bar_chart.dart';
import '../../../core/widgets/skeleton.dart';
import '../../../core/widgets/stat_tile.dart';
import '../../../core/widgets/state_views.dart';
import '../data/health_models.dart';
import '../data/health_repository.dart';

/// Activity statistics (explicitly not medical).
class HealthPage extends ConsumerStatefulWidget {
  const HealthPage({super.key});

  @override
  ConsumerState<HealthPage> createState() => _HealthPageState();
}

class _HealthPageState extends ConsumerState<HealthPage> {
  String _range = 'week';

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final summary = ref.watch(healthSummaryProvider(_range));
    return Scaffold(
      appBar: AppBar(
        title: Text(l.healthTitle),
        actions: [IconButton(tooltip: l.weeklyTitle, icon: const Icon(Icons.summarize_outlined), onPressed: () => context.push('/weekly-report'))],
      ),
      body: ListView(
        padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter, vertical: AppSpacing.sm),
        children: [
          SegmentedButton<String>(
            showSelectedIcon: false,
            segments: [ButtonSegment(value: 'week', label: Text(l.healthWeek)), ButtonSegment(value: 'month', label: Text(l.healthMonth))],
            selected: {_range},
            onSelectionChanged: (s) => setState(() => _range = s.first),
          ),
          const SizedBox(height: AppSpacing.lg),
          AsyncView(
            value: summary,
            onRetry: () => ref.invalidate(healthSummaryProvider(_range)),
            skeleton: const Shimmer(child: Column(children: [SkeletonBox(height: 170, radius: AppRadius.md), SizedBox(height: AppSpacing.lg), SkeletonBox(height: 120, radius: AppRadius.md)])),
            data: (s) => _Body(summary: s, month: _range == 'month'),
          ),
        ],
      ),
    );
  }
}

class _Body extends StatelessWidget {
  const _Body({required this.summary, required this.month});

  final HealthSummaryData summary;
  final bool month;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final s = summary;
    final goal = s.days.isEmpty ? 0 : s.days.last.goal;

    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      AppCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(l.healthTotal, style: context.text.labelMedium),
          Text('${Fa.number(s.totalSteps)} ${l.unitSteps}', style: context.text.headlineSmall),
          Text(l.healthGoalDays(Fa.digits(s.goalDays)), style: context.text.bodySmall),
          const SizedBox(height: AppSpacing.lg),
          MiniBarChart(
            height: 110,
            values: [for (final d in s.days) d.steps],
            goal: goal,
            highlightIndex: s.days.length - 1,
            labels: [
              for (var i = 0; i < s.days.length; i++)
                month ? (i % 7 == 0 ? Fa.digits(FaDate.dayOfMonth(s.days[i].date)) : null) : FaDate.weekdayShort(s.days[i].date),
            ],
            semanticLabel: l.healthTitle,
          ),
        ]),
      ),
      const SizedBox(height: AppSpacing.md),
      AppCard(
        child: Column(children: [
          Row(children: [
            Expanded(child: StatTile(value: Fa.decimal(s.totalDistanceM / 1000), unit: l.unitKm, label: l.homeDistance)),
            Expanded(child: StatTile(value: Fa.number(s.totalCalories.round()), unit: l.homeUnitKcal, label: l.homeCalories)),
            Expanded(child: StatTile(value: Fa.number(s.totalActiveMinutes), unit: l.homeUnitMin, label: l.homeActiveTime)),
          ]),
          const Padding(padding: EdgeInsets.symmetric(vertical: AppSpacing.md), child: Divider()),
          Row(children: [
            Expanded(child: StatTile(value: Fa.number(s.avgDaily), unit: '', label: l.healthAvgDaily)),
            Expanded(child: StatTile(value: Fa.number(s.avgWeekly), unit: '', label: l.healthAvgWeekly)),
            Expanded(child: StatTile(value: Fa.number(s.avgMonthly), unit: '', label: l.healthAvgMonthly)),
          ]),
        ]),
      ),
      SectionHeader(title: l.healthStreak),
      AppCard(child: Text(l.healthStreakValue(Fa.digits(s.streakCurrent), Fa.digits(s.streakLongest)), style: context.text.titleSmall)),
      SectionHeader(title: l.healthRecords),
      AppCard(
        padding: EdgeInsets.zero,
        child: Column(children: [
          for (final (label, value) in [(l.recordBestDay, s.bestDay), (l.recordBestWeek, s.bestWeek), (l.recordBestSession, s.bestSession)])
            ListTile(
              leading: Icon(Icons.emoji_events_outlined, color: p.goldInk),
              title: Text(label),
              trailing: Text('${Fa.number(value)} ${l.unitSteps}', style: context.text.titleSmall),
            ),
        ]),
      ),
      const SizedBox(height: AppSpacing.lg),
      Text(l.healthDisclaimer, style: context.text.bodySmall?.copyWith(color: p.inkSubtle)),
      const SizedBox(height: AppSpacing.xl),
    ]);
  }
}

class WeeklyReportPage extends ConsumerWidget {
  const WeeklyReportPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    return Scaffold(
      appBar: AppBar(title: Text(l.weeklyTitle)),
      body: AsyncView(
        value: ref.watch(weeklyReportProvider),
        onRetry: () => ref.invalidate(weeklyReportProvider),
        data: (r) => ListView(
          padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
          children: [
            Text(l.weeklyThisWeek, style: context.text.labelMedium),
            Text('${Fa.number(r.steps)} ${l.unitSteps}', style: context.text.displaySmall),
            const SizedBox(height: AppSpacing.xs),
            if (r.changePercent == null)
              Text(l.weeklyNoCompare, style: context.text.bodySmall)
            else
              Text(
                r.changePercent! >= 0 ? l.weeklyChangeUp(Fa.percent(r.changePercent!)) : l.weeklyChangeDown(Fa.percent(-r.changePercent!)),
                style: context.text.titleSmall?.copyWith(color: r.changePercent! >= 0 ? p.green : p.danger),
              ),
            const SizedBox(height: AppSpacing.xl),
            AppCard(
              child: Column(children: [
                Row(children: [
                  Expanded(child: StatTile(value: Fa.decimal(r.distanceM / 1000), unit: l.unitKm, label: l.homeDistance)),
                  Expanded(child: StatTile(value: Fa.number(r.calories.round()), unit: l.homeUnitKcal, label: l.homeCalories)),
                ]),
                const SizedBox(height: AppSpacing.lg),
                Row(children: [
                  Expanded(child: StatTile(value: Fa.signedPoints(r.points), unit: l.pointsUnit, label: l.pointsUnit)),
                  Expanded(child: StatTile(value: Fa.number(r.activeMinutes), unit: l.homeUnitMin, label: l.homeActiveTime)),
                ]),
              ]),
            ),
            const SizedBox(height: AppSpacing.md),
            AppCard(child: Text(l.weeklyGoalDays(Fa.digits(r.goalDays), Fa.digits(r.daysElapsed)), style: context.text.titleSmall)),
          ],
        ),
      ),
    );
  }
}
