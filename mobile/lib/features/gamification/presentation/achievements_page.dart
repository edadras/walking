import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/state_views.dart';
import '../../../core/widgets/trail.dart';
import '../data/gamification_models.dart';
import '../data/gamification_repository.dart';

IconData achievementIcon(String name) => switch (name) {
      'footsteps' => Icons.directions_walk_rounded,
      'bolt' => Icons.bolt_rounded,
      'chain' => Icons.link_rounded,
      'trail' => Icons.timeline_rounded,
      'route' => Icons.route_rounded,
      'calendar' => Icons.calendar_month_rounded,
      'flag' => Icons.flag_rounded,
      _ => Icons.emoji_events_rounded,
    };

class AchievementsPage extends ConsumerWidget {
  const AchievementsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.achievementsTitle)),
      body: AsyncView(
        value: ref.watch(achievementsProvider),
        onRetry: () => ref.invalidate(achievementsProvider),
        data: (items) {
          final unlocked = items.where((a) => a.unlocked).length;
          return CustomScrollView(slivers: [
            SliverPadding(
              padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, AppSpacing.lg, AppSpacing.gutter, AppSpacing.sm),
              sliver: SliverToBoxAdapter(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(l.achievementsCount(Fa.digits(unlocked), Fa.digits(items.length)), style: context.text.titleMedium),
                  const SizedBox(height: AppSpacing.sm),
                  const _LevelCard(),
                ]),
              ),
            ),
            SliverPadding(
              padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
              sliver: SliverGrid.builder(
                gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(maxCrossAxisExtent: 180, mainAxisSpacing: AppSpacing.md, crossAxisSpacing: AppSpacing.md, childAspectRatio: 0.72),
                itemCount: items.length,
                itemBuilder: (_, i) => _Medal(item: items[i]),
              ),
            ),
          ]);
        },
      ),
    );
  }
}

class _LevelCard extends ConsumerWidget {
  const _LevelCard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final progress = ref.watch(progressProvider).value;
    if (progress == null) return const SizedBox.shrink();
    return LevelBar(level: progress.$1);
  }
}

/// Level, title and XP bar (used on the profile too).
class LevelBar extends StatelessWidget {
  const LevelBar({super.key, required this.level, this.onTap});

  final LevelProgress level;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    return AppCard(
      onTap: onTap,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text(l.levelLabel(Fa.digits(level.level), level.title), style: context.text.titleSmall)),
          Text(level.nextMin == null ? l.levelMax : l.levelXp(Fa.number(level.xp), Fa.number(level.nextMin!)), style: context.text.labelSmall),
        ]),
        const SizedBox(height: AppSpacing.sm),
        ClipRRect(
          borderRadius: BorderRadius.circular(3),
          child: LinearProgressIndicator(value: level.fraction, minHeight: 6, color: p.gold, backgroundColor: p.surfaceSunken),
        ),
      ]),
    );
  }
}

class _Medal extends StatelessWidget {
  const _Medal({required this.item});

  final AchievementItem item;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final a = item;
    return Semantics(
      label: '${a.name}. ${a.description}',
      child: Column(children: [
        SizedBox(
          width: 88,
          height: 88,
          child: CustomPaint(
            painter: DotRingPainter(
              progress: a.unlocked ? 1 : a.fraction,
              filled: a.unlocked ? p.gold : p.green,
              empty: p.border,
              accent: p.goldInk,
              dots: 28,
              dotRadius: 2.6,
            ),
            child: Center(
              child: CircleAvatar(
                radius: 28,
                backgroundColor: a.unlocked ? p.goldSoft : p.surfaceSunken,
                child: Icon(achievementIcon(a.icon), color: a.unlocked ? p.goldInk : p.inkSubtle),
              ),
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(a.name, style: context.text.titleSmall, textAlign: TextAlign.center, maxLines: 1, overflow: TextOverflow.ellipsis),
        const SizedBox(height: AppSpacing.xxs),
        Text(a.description, style: context.text.labelSmall, textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis),
        const SizedBox(height: AppSpacing.xxs),
        Text(
          a.unlocked ? FaDate.dayMonth(a.unlockedAt!) : '${Fa.number(a.progress)} / ${Fa.number(a.threshold)}',
          style: context.text.labelSmall?.copyWith(color: a.unlocked ? p.goldInk : p.inkSubtle),
        ),
      ]),
    );
  }
}
