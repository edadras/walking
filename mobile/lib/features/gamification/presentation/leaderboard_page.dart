import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/state_views.dart';
import '../data/gamification_models.dart';
import '../data/gamification_repository.dart';
import '../../../core/widgets/net_image.dart';

/// Ranked by verified steps only. Users who hid themselves never appear.
class LeaderboardPage extends ConsumerWidget {
  const LeaderboardPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return DefaultTabController(
      length: 3,
      initialIndex: 1,
      child: Scaffold(
        appBar: AppBar(
          title: Text(l.leaderboardTitle),
          actions: [IconButton(tooltip: l.friendsTitle, icon: const Icon(Icons.people_outline_rounded), onPressed: () => context.push('/friends'))],
          bottom: TabBar(tabs: [Tab(text: l.lbToday), Tab(text: l.lbWeek), Tab(text: l.lbMonth)]),
        ),
        body: const TabBarView(children: [_Board(period: 'day'), _Board(period: 'week'), _Board(period: 'month')]),
      ),
    );
  }
}

class _Board extends ConsumerWidget {
  const _Board({required this.period});

  final String period;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return RefreshIndicator(
      color: context.palette.green,
      onRefresh: () async => ref.invalidate(leaderboardProvider(period)),
      child: AsyncView(
        value: ref.watch(leaderboardProvider(period)),
        onRetry: () => ref.invalidate(leaderboardProvider(period)),
        data: (b) => ListView(
          padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
          children: [
            if (!b.visible)
              AppCard(color: context.palette.surfaceSunken, child: Text(l.lbHidden, style: context.text.bodySmall))
            else if (b.me != null && !b.entries.any((e) => e.isMe))
              _Row(entry: b.me!, highlight: true),
            const SizedBox(height: AppSpacing.sm),
            if (b.entries.isEmpty)
              EmptyView(title: l.lbEmpty, compact: true)
            else
              for (final e in b.entries) _Row(entry: e, highlight: e.isMe),
            const SizedBox(height: AppSpacing.lg),
            Text(l.lbPrivacy, style: context.text.bodySmall?.copyWith(color: context.palette.inkSubtle)),
          ],
        ),
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.entry, this.highlight = false});

  final LeaderboardEntry entry;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final medal = switch (entry.rank) { 1 => p.gold, 2 => p.inkSubtle, 3 => p.goldInk, _ => null };
    return Container(
      margin: const EdgeInsetsDirectional.only(bottom: AppSpacing.xs),
      padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.md, vertical: AppSpacing.sm),
      decoration: BoxDecoration(color: highlight ? p.greenSoft : null, borderRadius: AppRadius.smAll),
      child: Row(children: [
        SizedBox(
          width: 36,
          child: medal != null
              ? Icon(Icons.workspace_premium_rounded, color: medal)
              : Text(Fa.digits(entry.rank), style: context.text.titleSmall, textAlign: TextAlign.center),
        ),
        const SizedBox(width: AppSpacing.sm),
        NetAvatar(
          radius: 18,
          backgroundColor: p.surfaceSunken,
          url: entry.avatarUrl,
          child: Text(entry.name.isEmpty ? '؟' : entry.name.characters.first),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(entry.isMe ? '${entry.name} (${l.lbYou})' : entry.name, style: context.text.bodyLarge, maxLines: 1, overflow: TextOverflow.ellipsis),
            Text(l.profileLevel(Fa.digits(entry.level)), style: context.text.labelSmall),
          ]),
        ),
        Text('${Fa.number(entry.steps)} ${l.unitSteps}', style: context.text.titleSmall),
      ]),
    );
  }
}
