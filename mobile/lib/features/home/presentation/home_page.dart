import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/permissions/permission_primer.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/bar_chart.dart';
import '../../../core/widgets/skeleton.dart';
import '../../../core/widgets/state_views.dart';
import '../../../core/widgets/stat_tile.dart';
import '../../../core/widgets/step_ring.dart';
import '../../activity/application/active_walk_controller.dart';
import '../../activity/application/activity_providers.dart';
import '../../activity/application/tracking_service.dart';
import '../../auth/application/session_controller.dart';
import '../../gamification/data/gamification_models.dart';
import '../../wallet/data/wallet_models.dart';

class HomePage extends ConsumerWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final me = ref.watch(meProvider);
    final home = ref.watch(homeProvider);
    final l = context.l10n;
    final p = context.palette;
    final now = DateTime.now();

    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          color: p.green,
          onRefresh: () => ref.read(homeProvider.notifier).refresh(),
          child: ListView(
            padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter, vertical: AppSpacing.lg),
            children: [
              Row(children: [
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(l.homeGreeting(me.publicName), style: context.text.headlineSmall),
                    Text(l.homeToday('${FaDate.weekday(now)} ${FaDate.dayMonth(now)}'), style: context.text.bodySmall),
                  ]),
                ),
                _Bell(unread: home.value?.data.unread ?? 0),
              ]),
              const SizedBox(height: AppSpacing.lg),
              const _TrackingBanner(),
              AsyncView(
                value: home,
                onRetry: () => ref.invalidate(homeProvider),
                skeleton: const _HomeSkeleton(),
                data: (view) => _HomeBody(view: view),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _HomeBody extends ConsumerWidget {
  const _HomeBody({required this.view});

  final HomeView view;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    final t = view.data.today;
    final remaining = (view.goal - view.steps).clamp(0, view.goal);
    final walk = ref.watch(activeWalkProvider);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: AppSpacing.sm),
        Center(child: StepRing(steps: view.steps, goal: view.goal)),
        const SizedBox(height: AppSpacing.md),
        Center(
          child: Text(
            remaining == 0 ? l.homeGoalReached : l.homeRemaining(Fa.number(remaining)),
            style: context.text.bodyMedium?.copyWith(color: p.inkMuted),
          ),
        ),
        if (view.awaitingVerification > 0) ...[
          const SizedBox(height: AppSpacing.xs),
          Center(
            child: Text(l.homeAwaiting(Fa.number(view.awaitingVerification)), style: context.text.labelSmall?.copyWith(color: p.inkSubtle)),
          ),
        ],
        const SizedBox(height: AppSpacing.xl),
        AppButton(
          label: walk is WalkRunning ? l.homeWalkInProgress : l.homeStartWalk,
          icon: walk is WalkRunning ? Icons.radio_button_checked_rounded : Icons.directions_walk_rounded,
          variant: walk is WalkRunning ? AppButtonVariant.secondary : AppButtonVariant.primary,
          onPressed: () => context.push('/walk'),
        ),
        const SizedBox(height: AppSpacing.lg),
        if (view.data.streak != null) ...[
          _StreakRow(streak: view.data.streak!),
          const SizedBox(height: AppSpacing.md),
        ],
        if (view.data.wallet != null) ...[
          _PointsCard(points: t.points, wallet: view.data.wallet!),
          const SizedBox(height: AppSpacing.md),
        ],
        AppCard(
          child: Row(children: [
            Expanded(child: StatTile(value: Fa.decimal(t.distanceM / 1000), unit: l.homeUnitKm, label: l.homeDistance, icon: Icons.route_outlined)),
            Expanded(
              child: StatTile(value: Fa.number(t.caloriesKcal.round()), unit: l.homeUnitKcal, label: l.homeCalories, icon: Icons.local_fire_department_outlined),
            ),
            Expanded(child: StatTile(value: Fa.number(t.activeMinutes), unit: l.homeUnitMin, label: l.homeActiveTime, icon: Icons.timer_outlined)),
          ]),
        ),
        if (view.data.water != null || view.data.challenge != null) ...[
          const SizedBox(height: AppSpacing.md),
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            if (view.data.water != null) Expanded(child: _WaterCard(water: view.data.water!)),
            if (view.data.water != null && view.data.challenge != null) const SizedBox(width: AppSpacing.md),
            if (view.data.challenge != null) Expanded(child: _ChallengeCard(challenge: view.data.challenge!)),
          ]),
        ],
        SectionHeader(title: l.homeThisWeek, actionLabel: l.navActivity, onAction: () => context.go('/activity')),
        AppCard(
          child: MiniBarChart(
            height: 88,
            values: [for (final d in view.data.week) d == view.data.week.last ? view.steps : d.steps],
            goal: view.goal,
            highlightIndex: view.data.week.length - 1,
            labels: [for (final d in view.data.week) FaDate.weekdayShort(d.date)],
            semanticLabel: l.homeThisWeek,
          ),
        ),
        if (t.lastSyncedAt != null) ...[
          const SizedBox(height: AppSpacing.md),
          Center(child: Text(l.homeSyncedAt(FaDate.relative(t.lastSyncedAt!)), style: context.text.labelSmall)),
        ],
      ],
    );
  }
}

class _Bell extends StatelessWidget {
  const _Bell({required this.unread});

  final int unread;

  @override
  Widget build(BuildContext context) => IconButton(
        tooltip: context.l10n.homeNotifications,
        onPressed: () => context.push('/notifications'),
        icon: Badge(
          isLabelVisible: unread > 0,
          label: Text(Fa.digits(unread > 99 ? 99 : unread)),
          child: const Icon(Icons.notifications_none_rounded),
        ),
      );
}

/// Seven dots for this week (Sat→Fri): reached, missed, or still ahead.
class _StreakRow extends StatelessWidget {
  const _StreakRow({required this.streak});

  final StreakWeek streak;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    return AppCard(
      onTap: () => context.push('/achievements'),
      child: Row(children: [
        Icon(Icons.local_fire_department_rounded, color: streak.current > 0 ? p.goldInk : p.inkSubtle),
        const SizedBox(width: AppSpacing.xs),
        Text(l.homeStreak7(Fa.digits(streak.current)), style: context.text.titleSmall),
        const Spacer(),
        for (final d in streak.days)
          Padding(
            padding: const EdgeInsetsDirectional.only(start: AppSpacing.xs),
            child: Column(children: [
              Container(
                width: 14,
                height: 14,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: d.reached ? p.green : (d.future ? null : p.surfaceSunken),
                  border: Border.all(color: d.reached ? p.green : p.border),
                ),
              ),
              const SizedBox(height: 2),
              Text(FaDate.weekdayShort(d.date), style: context.text.labelSmall),
            ]),
          ),
      ]),
    );
  }
}

class _WaterCard extends StatelessWidget {
  const _WaterCard({required this.water});

  final Map<String, dynamic> water;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final total = (water['total_ml'] as num? ?? 0).toInt();
    final goal = (water['goal_ml'] as num? ?? 0).toInt();
    return AppCard(
      onTap: () => context.push('/water'),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Icon(Icons.water_drop_outlined, color: p.info, size: 18),
          const SizedBox(width: AppSpacing.xs),
          Text(l.homeWater, style: context.text.labelMedium),
        ]),
        const SizedBox(height: AppSpacing.xs),
        Text('${Fa.number(total)} ml', style: context.text.titleMedium),
        const SizedBox(height: AppSpacing.xs),
        ClipRRect(
          borderRadius: BorderRadius.circular(2),
          child: LinearProgressIndicator(value: goal == 0 ? 0 : (total / goal).clamp(0, 1), minHeight: 4, color: p.info, backgroundColor: p.surfaceSunken),
        ),
      ]),
    );
  }
}

class _ChallengeCard extends StatelessWidget {
  const _ChallengeCard({required this.challenge});

  final Map<String, dynamic> challenge;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final progress = (challenge['progress'] as num? ?? 0).toInt();
    final target = (challenge['target'] as num? ?? 0).toInt();
    return AppCard(
      onTap: () => context.push('/challenges/${challenge['id']}'),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Icon(Icons.flag_outlined, color: p.green, size: 18),
          const SizedBox(width: AppSpacing.xs),
          Expanded(child: Text(l.homeChallenge, style: context.text.labelMedium)),
        ]),
        const SizedBox(height: AppSpacing.xs),
        Text(challenge['title'] as String? ?? '', style: context.text.titleSmall, maxLines: 1, overflow: TextOverflow.ellipsis),
        const SizedBox(height: AppSpacing.xs),
        ClipRRect(
          borderRadius: BorderRadius.circular(2),
          child: LinearProgressIndicator(value: target == 0 ? 0 : (progress / target).clamp(0, 1), minHeight: 4, color: p.green, backgroundColor: p.surfaceSunken),
        ),
      ]),
    );
  }
}

/// Today's points and what the balance is worth (tap → wallet).
class _PointsCard extends StatelessWidget {
  const _PointsCard({required this.points, required this.wallet});

  final int points;
  final WalletBalance wallet;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    return AppCard(
      onTap: () => context.push('/wallet'),
      child: Row(children: [
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(l.homePointsCard, style: context.text.labelMedium),
            const SizedBox(height: AppSpacing.xxs),
            Text(l.pointsPlus(Fa.number(points)), style: context.text.titleMedium?.copyWith(color: p.goldInk, fontWeight: FontWeight.w800)),
          ]),
        ),
        Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
          PointsChip(label: Fa.number(wallet.available)),
          const SizedBox(height: AppSpacing.xxs),
          Text(Fa.rial(wallet.rialValue), style: context.text.labelSmall),
        ]),
        const SizedBox(width: AppSpacing.sm),
        Icon(Icons.chevron_left_rounded, color: p.inkSubtle, textDirection: TextDirection.ltr),
      ]),
    );
  }
}

/// Asks for the activity permission in context (never on first launch).
class _TrackingBanner extends ConsumerWidget {
  const _TrackingBanner();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final availability = ref.watch(trackingAvailabilityProvider).value;
    final l = context.l10n;
    final p = context.palette;

    if (availability == null || availability == TrackingAvailability.ready) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.lg),
      child: AppCard(
        color: availability == TrackingAvailability.noSensor ? p.surface : p.greenSoft,
        borderColor: availability == TrackingAvailability.noSensor ? p.border : p.greenSoft,
        child: availability == TrackingAvailability.noSensor
            ? Text(l.trackingNoSensor, style: context.text.bodySmall)
            : Row(children: [
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(l.trackingOffTitle, style: context.text.titleSmall),
                    const SizedBox(height: AppSpacing.xxs),
                    Text(l.trackingOffBody, style: context.text.bodySmall),
                  ]),
                ),
                const SizedBox(width: AppSpacing.md),
                AppButton(
                  expand: false,
                  label: l.trackingEnable,
                  onPressed: () async {
                    if (await PermissionPrimer.ensure(context, AppPermission.activity)) {
                      await ref.read(stepPlatformProvider).startPassive();
                      ref.invalidate(trackingAvailabilityProvider);
                    }
                  },
                ),
              ]),
      ),
    );
  }
}

class _HomeSkeleton extends StatelessWidget {
  const _HomeSkeleton();

  @override
  Widget build(BuildContext context) => const Shimmer(
        child: Column(children: [
          SizedBox(height: AppSpacing.sm),
          SkeletonBox(height: 244, circle: true),
          SizedBox(height: AppSpacing.xl),
          SkeletonBox(width: 180, height: 12),
          SizedBox(height: AppSpacing.xl),
          SkeletonBox(height: 48),
          SizedBox(height: AppSpacing.lg),
          SkeletonBox(height: 96, radius: AppRadius.md),
        ]),
      );
}
