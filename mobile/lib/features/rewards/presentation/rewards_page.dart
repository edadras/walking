import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/skeleton.dart';
import '../../../core/widgets/stat_tile.dart';
import '../../../core/widgets/state_views.dart';
import '../../ads/presentation/ad_slot.dart';
import '../../ads/presentation/rewarded_ad_page.dart';
import '../data/rewards_models.dart';
import '../data/rewards_repository.dart';

/// Reward Center: today's earnings and exactly how to earn more.
class RewardsPage extends ConsumerWidget {
  const RewardsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final center = ref.watch(rewardCenterProvider);
    return Scaffold(
      appBar: AppBar(
        title: Text(l.rewardsTitle),
        actions: [
          IconButton(tooltip: l.questsTitle, icon: const Icon(Icons.flag_circle_outlined), onPressed: () => context.push('/quests')),
          IconButton(tooltip: l.challengesTitle, icon: const Icon(Icons.flag_outlined), onPressed: () => context.push('/challenges')),
          IconButton(tooltip: l.leaderboardTitle, icon: const Icon(Icons.leaderboard_outlined), onPressed: () => context.push('/leaderboard')),
        ],
      ),
      body: RefreshIndicator(
        color: context.palette.green,
        onRefresh: () async => ref.invalidate(rewardCenterProvider),
        child: ListView(
          padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter, vertical: AppSpacing.sm),
          children: [
            AsyncView(
              value: center,
              onRetry: () => ref.invalidate(rewardCenterProvider),
              skeleton: const Shimmer(
                child: Column(children: [
                  SkeletonBox(height: 140, radius: AppRadius.md),
                  SizedBox(height: AppSpacing.xxl),
                  SkeletonBox(height: 220, radius: AppRadius.md),
                ]),
              ),
              data: (c) => _Body(center: c),
            ),
          ],
        ),
      ),
    );
  }
}

class _Body extends StatelessWidget {
  const _Body({required this.center});

  final RewardCenter center;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final c = center;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AppCard(
          padding: const EdgeInsetsDirectional.all(AppSpacing.xl),
          onTap: () => context.push('/wallet'),
          child: Row(children: [
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(l.rewardsToday, style: context.text.labelMedium),
                const SizedBox(height: AppSpacing.xs),
                Text(Fa.signedPoints(c.pointsToday), style: context.text.displaySmall?.copyWith(color: c.pointsToday > 0 ? p.goldInk : p.ink)),
                Text(l.rewardsTodayOf(Fa.number(c.dailyCap)), style: context.text.bodySmall),
              ]),
            ),
            Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
              PointsChip(label: Fa.number(c.wallet.available), large: true),
              const SizedBox(height: AppSpacing.xs),
              Text(Fa.rial(c.wallet.rialValue), style: context.text.labelSmall),
              if (c.wallet.pending > 0) Text('${Fa.number(c.wallet.pending)} ${l.walletPending}', style: context.text.labelSmall),
            ]),
          ]),
        ),
        const RewardedAdCard(),
        SectionHeader(title: l.rewardsHowTitle),
        AppCard(
          padding: EdgeInsets.zero,
          child: Column(children: [
            _Way(
              icon: Icons.directions_walk_rounded,
              title: l.rewardsRate(Fa.number(c.stepsPerUnit)),
              subtitle: l.rewardsRemaining(Fa.number(c.remainingRewardableSteps)),
              points: '+${Fa.number(c.pointsPerUnit)}',
            ),
            if (c.goalBonus > 0)
              _Way(
                icon: Icons.flag_rounded,
                title: l.rewardsGoalBonus,
                subtitle: '${Fa.number(c.verifiedSteps.clamp(0, c.goal))} / ${Fa.number(c.goal)}',
                points: c.goalReached ? l.rewardsGoalDone : '+${Fa.number(c.goalBonus)}',
                progress: c.goal == 0 ? 0 : c.verifiedSteps / c.goal,
                done: c.goalReached,
              ),
            for (final e in (c.streakBonuses.entries.toList()..sort((a, b) => a.key.compareTo(b.key))))
              _Way(icon: Icons.linear_scale_rounded, title: l.rewardsStreak(Fa.digits(e.key)), points: '+${Fa.number(e.value)}'),
          ]),
        ),
        const SizedBox(height: AppSpacing.md),
        Row(children: [
          Expanded(child: _Shortcut(icon: Icons.place_rounded, label: l.rewardsNearby, route: '/nearby')),
          const SizedBox(width: AppSpacing.sm),
          Expanded(child: _Shortcut(icon: Icons.confirmation_number_rounded, label: l.rewardsCoupons, route: '/coupons')),
        ]),
        const SizedBox(height: AppSpacing.sm),
        Row(children: [
          Expanded(child: _Shortcut(icon: Icons.flag_rounded, label: l.challengesTitle, route: '/challenges')),
          const SizedBox(width: AppSpacing.sm),
          Expanded(child: _Shortcut(icon: Icons.group_add_rounded, label: l.referralTitle, route: '/referral')),
          const SizedBox(width: AppSpacing.sm),
          Expanded(child: _Shortcut(icon: Icons.emoji_events_rounded, label: l.achievementsTitle, route: '/achievements')),
        ]),
        if (c.multiplierNow > 1) ...[
          const SizedBox(height: AppSpacing.md),
          AppCard(
            color: p.goldSoft,
            borderColor: p.goldSoft,
            child: Text(l.rewardsMultiplierNow(Fa.decimal(c.multiplierNow)), style: context.text.titleSmall?.copyWith(color: p.goldInk)),
          ),
        ],
        if (c.upcomingMultipliers.isNotEmpty) ...[
          SectionHeader(title: l.rewardsUpcoming),
          for (final m in c.upcomingMultipliers)
            Padding(
              padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.sm),
              child: Row(children: [
                Text('${FaDate.weekday(m.date)} ${FaDate.dayMonth(m.date)}', style: context.text.bodyLarge),
                const Spacer(),
                PointsChip(label: '×${Fa.decimal(m.multiplier)}'),
              ]),
            ),
        ],
        const AdSlot(placement: 'rewards_native'),
        if (c.recent.isNotEmpty) ...[
          SectionHeader(title: l.rewardsRecent),
          for (final r in c.recent) _RecentRow(item: r),
        ],
      ],
    );
  }
}

class _Shortcut extends StatelessWidget {
  const _Shortcut({required this.icon, required this.label, required this.route});

  final IconData icon;
  final String label;
  final String route;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return AppCard(
      padding: const EdgeInsetsDirectional.symmetric(vertical: AppSpacing.md, horizontal: AppSpacing.sm),
      onTap: () => context.push(route),
      child: Column(children: [
        Icon(icon, color: p.green),
        const SizedBox(height: AppSpacing.xs),
        Text(label, style: context.text.labelMedium, textAlign: TextAlign.center, maxLines: 1, overflow: TextOverflow.ellipsis),
      ]),
    );
  }
}

class _Way extends StatelessWidget {
  const _Way({required this.icon, required this.title, required this.points, this.subtitle, this.progress, this.done = false});

  final IconData icon;
  final String title;
  final String? subtitle;
  final String points;
  final double? progress;
  final bool done;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Padding(
      padding: const EdgeInsetsDirectional.all(AppSpacing.lg),
      child: Row(children: [
        Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(color: p.greenSoft, borderRadius: AppRadius.smAll),
          child: Icon(icon, color: p.green, size: 20),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: context.text.titleSmall),
            if (subtitle != null) Text(subtitle!, style: context.text.bodySmall),
            if (progress != null) ...[
              const SizedBox(height: AppSpacing.sm),
              ClipRRect(
                borderRadius: BorderRadius.circular(2),
                child: LinearProgressIndicator(value: progress!.clamp(0, 1), minHeight: 4, color: p.green, backgroundColor: p.surfaceSunken),
              ),
            ],
          ]),
        ),
        const SizedBox(width: AppSpacing.md),
        done ? Icon(Icons.check_circle_rounded, color: p.green) : PointsChip(label: points),
      ]),
    );
  }
}

class _RecentRow extends StatelessWidget {
  const _RecentRow({required this.item});

  final RewardItem item;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final title = switch (item.kind) {
      'walking' => l.rewardKindWalking,
      'goal_bonus' => l.rewardKindGoal,
      'streak_bonus' => l.rewardKindStreak,
      _ => l.rewardKindOther,
    };
    final status = switch (item.status) {
      'pending' => l.rewardPending,
      'denied' => l.rewardDenied,
      'reversed' => l.rewardReversed,
      _ => null,
    };
    return Padding(
      padding: const EdgeInsetsDirectional.symmetric(vertical: AppSpacing.sm),
      child: Row(children: [
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: context.text.bodyLarge),
            Text([FaDate.relative(item.createdAt), ?status, if (item.multiplier > 1) '×${Fa.decimal(item.multiplier)}'].join(' · '), style: context.text.bodySmall),
          ]),
        ),
        Text(Fa.signedPoints(item.points), style: context.text.titleSmall?.copyWith(color: item.points > 0 ? p.goldInk : p.inkSubtle)),
      ]),
    );
  }
}
