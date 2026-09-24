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
              Text(l.homeGreeting(me.publicName), style: context.text.headlineSmall),
              Text(l.homeToday('${FaDate.weekday(now)} ${FaDate.dayMonth(now)}'), style: context.text.bodySmall),
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
