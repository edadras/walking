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
import '../../../core/widgets/state_views.dart';
import '../../../core/widgets/stat_tile.dart';
import '../application/activity_providers.dart';
import '../application/tracking_service.dart';
import '../data/activity_models.dart';

/// Daily timeline: pick one of the last 7 days, see its totals, steps per hour
/// and every recorded session.
class ActivityPage extends ConsumerStatefulWidget {
  const ActivityPage({super.key});

  @override
  ConsumerState<ActivityPage> createState() => _ActivityPageState();
}

class _ActivityPageState extends ConsumerState<ActivityPage> {
  /// Days back from today (0 = today).
  int _offset = 0;

  String? get _date {
    if (_offset == 0) return null;
    final clock = ref.read(dayClockProvider);
    return clock.dateOf(DateTime.now().subtract(Duration(days: _offset)));
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final day = ref.watch(dayActivityProvider(_date));

    return Scaffold(
      appBar: AppBar(title: Text(l.activityTitle)),
      body: RefreshIndicator(
        color: context.palette.green,
        onRefresh: () async {
          await ref.read(trackingServiceProvider).sync();
          ref.invalidate(dayActivityProvider(_date));
          ref.invalidate(homeProvider);
        },
        child: ListView(
          padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter, vertical: AppSpacing.sm),
          children: [
            _DayPicker(selected: _offset, onSelect: (o) => setState(() => _offset = o)),
            const SizedBox(height: AppSpacing.lg),
            AsyncView(
              value: day,
              onRetry: () => ref.invalidate(dayActivityProvider(_date)),
              skeleton: const _DaySkeleton(),
              data: (d) => _DayBody(day: d),
            ),
          ],
        ),
      ),
    );
  }
}

class _DayPicker extends StatelessWidget {
  const _DayPicker({required this.selected, required this.onSelect});

  final int selected;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final now = DateTime.now();
    return Row(
      children: [
        for (var offset = 0; offset < 7; offset++)
          Expanded(
            child: Builder(builder: (context) {
              final date = now.subtract(Duration(days: offset));
              final on = offset == selected;
              return Semantics(
                selected: on,
                button: true,
                label: offset == 0 ? context.l10n.activityToday : FaDate.long(date),
                excludeSemantics: true,
                child: InkWell(
                  borderRadius: AppRadius.smAll,
                  onTap: () => onSelect(offset),
                  child: AnimatedContainer(
                    duration: AppMotion.base,
                    margin: const EdgeInsetsDirectional.symmetric(horizontal: 2),
                    padding: const EdgeInsetsDirectional.symmetric(vertical: AppSpacing.sm),
                    decoration: BoxDecoration(
                      color: on ? p.green : Colors.transparent,
                      borderRadius: AppRadius.smAll,
                      border: Border.all(color: on ? p.green : p.border),
                    ),
                    child: Column(children: [
                      Text(FaDate.weekdayShort(date), style: context.text.labelSmall?.copyWith(color: on ? Colors.white70 : p.inkSubtle)),
                      const SizedBox(height: 2),
                      Text(Fa.digits(FaDate.dayOfMonth(date)), style: context.text.titleSmall?.copyWith(color: on ? Colors.white : p.ink)),
                    ]),
                  ),
                ),
              );
            }),
          ),
      ],
    );
  }
}

class _DayBody extends StatelessWidget {
  const _DayBody({required this.day});

  final DayActivity day;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final s = day.summary;
    if (s == null || s.steps == 0) {
      return EmptyView(title: l.activityEmptyTitle, message: l.activityEmptyBody, compact: true);
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AppCard(
          child: Column(children: [
            Row(children: [
              Expanded(
                child: StatTile(
                  value: Fa.number(s.steps),
                  unit: l.activitySteps,
                  label: '${Fa.percent(s.goal == 0 ? 0 : s.steps * 100 / s.goal)} ${l.profileDailyGoal}',
                ),
              ),
              Expanded(child: StatTile(value: Fa.decimal(s.distanceM / 1000), unit: l.homeUnitKm, label: l.homeDistance)),
            ]),
            const SizedBox(height: AppSpacing.lg),
            Row(children: [
              Expanded(child: StatTile(value: Fa.number(s.caloriesKcal.round()), unit: l.homeUnitKcal, label: l.homeCalories)),
              Expanded(child: StatTile(value: Fa.number(s.activeMinutes), unit: l.homeUnitMin, label: l.homeActiveTime)),
            ]),
          ]),
        ),
        SectionHeader(title: l.activityHourly),
        AppCard(
          child: MiniBarChart(
            values: day.hourly,
            height: 96,
            labels: [for (var h = 0; h < 24; h++) h % 6 == 0 ? Fa.digits(h) : null],
            semanticLabel: l.activityHourly,
          ),
        ),
        SectionHeader(title: l.activityTimeline),
        for (final session in day.sessions.reversed) _SessionRow(session: session),
      ],
    );
  }
}

class _SessionRow extends StatelessWidget {
  const _SessionRow({required this.session});

  final WalkSession session;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    return InkWell(
      onTap: () => context.push('/activity/session/${session.id}'),
      borderRadius: AppRadius.smAll,
      child: Padding(
        padding: const EdgeInsetsDirectional.symmetric(vertical: AppSpacing.md),
        child: Row(
          children: [
            SizedBox(width: 52, child: Text(FaDate.time(session.startedAt), style: context.text.titleSmall)),
            Container(
              width: 8,
              height: 8,
              decoration: BoxDecoration(color: session.isActive ? p.green : p.border, shape: BoxShape.circle),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('${Fa.number(session.steps)} ${l.activitySteps}', style: context.text.bodyLarge?.copyWith(fontWeight: FontWeight.w600)),
                Text(
                  '${session.isActive ? l.activityActive : l.activityPassive} · ${FaDate.duration(Duration(seconds: session.durationS))}',
                  style: context.text.bodySmall,
                ),
              ]),
            ),
            _StatusChip(status: session.status, label: session.statusLabel),
          ],
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status, required this.label});

  final String status;
  final String label;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final (bg, fg) = switch (status) {
      'verified' => (p.greenSoft, p.greenStrong),
      'rejected' => (p.dangerSoft, p.danger),
      'partially_verified' || 'under_review' => (p.goldSoft, p.goldInk),
      _ => (p.surfaceSunken, p.inkMuted),
    };
    return Container(
      padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.sm, vertical: 2),
      decoration: BoxDecoration(color: bg, borderRadius: const BorderRadius.all(Radius.circular(AppRadius.xs))),
      child: Text(label, style: context.text.labelSmall?.copyWith(color: fg, fontWeight: FontWeight.w600)),
    );
  }
}

class _DaySkeleton extends StatelessWidget {
  const _DaySkeleton();

  @override
  Widget build(BuildContext context) => const Shimmer(
        child: Column(children: [
          SkeletonBox(height: 72, radius: AppRadius.md),
          SizedBox(height: AppSpacing.xxl),
          SkeletonBox(height: 120, radius: AppRadius.md),
          SizedBox(height: AppSpacing.xxl),
          SkeletonBox(height: 40),
          SizedBox(height: AppSpacing.md),
          SkeletonBox(height: 40),
        ]),
      );
}
