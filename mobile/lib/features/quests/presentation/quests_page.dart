import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/analytics/analytics.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/stat_tile.dart';
import '../../../core/widgets/state_views.dart';
import '../../wallet/data/wallet_repository.dart';
import '../data/quests.dart';

class QuestsPage extends ConsumerWidget {
  const QuestsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.questsTitle)),
      body: RefreshIndicator(
        color: context.palette.green,
        onRefresh: () async => ref.invalidate(questsProvider),
        child: AsyncView(
          value: ref.watch(questsProvider),
          onRetry: () => ref.invalidate(questsProvider),
          data: (quests) {
            if (quests.isEmpty) return ListView(children: [EmptyView(title: l.questsEmpty)]);
            final daily = quests.where((q) => q.period == 'daily').toList();
            final weekly = quests.where((q) => q.period == 'weekly').toList();
            return ListView(
              padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
              children: [
                if (daily.isNotEmpty) ...[
                  _Header(title: l.questsDaily, hint: l.questsDailyHint),
                  for (final q in daily) QuestCard(quest: q),
                ],
                if (weekly.isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.lg),
                  _Header(title: l.questsWeekly, hint: l.questsWeeklyHint),
                  for (final q in weekly) QuestCard(quest: q),
                ],
              ],
            );
          },
        ),
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.title, required this.hint});

  final String title;
  final String hint;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.sm),
        child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
          Text(title, style: context.text.titleMedium),
          const SizedBox(width: AppSpacing.sm),
          Expanded(child: Text(hint, style: context.text.labelSmall)),
        ]),
      );
}

class QuestCard extends ConsumerStatefulWidget {
  const QuestCard({super.key, required this.quest});

  final Quest quest;

  @override
  ConsumerState<QuestCard> createState() => _QuestCardState();
}

class _QuestCardState extends ConsumerState<QuestCard> {
  bool _busy = false;

  Future<void> _claim() async {
    setState(() => _busy = true);
    try {
      await claimQuest(ref.read(apiClientProvider), widget.quest.key);
      ref.read(analyticsProvider).track('reward_received', {'source': 'quest', 'points': widget.quest.rewardPoints});
      ref.invalidate(questsProvider);
      ref.invalidate(walletBalanceProvider);
      if (mounted) showAppSnack(context, context.l10n.questClaimed(Fa.number(widget.quest.rewardPoints)));
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final q = widget.quest;
    return Padding(
      padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.sm),
      child: AppCard(
        borderColor: q.claimable ? p.gold : null,
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(q.claimed ? Icons.check_circle_rounded : Icons.flag_circle_outlined, color: q.claimed ? p.green : p.inkMuted),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(q.title, style: context.text.titleSmall),
                if (q.description != null) Text(q.description!, style: context.text.bodySmall),
              ]),
            ),
            PointsChip(label: '+${Fa.number(q.rewardPoints)}'),
          ]),
          const SizedBox(height: AppSpacing.md),
          ClipRRect(
            borderRadius: BorderRadius.circular(3),
            child: LinearProgressIndicator(value: q.fraction, minHeight: 6, color: q.claimed ? p.green : p.gold, backgroundColor: p.surfaceSunken),
          ),
          const SizedBox(height: AppSpacing.xs),
          Row(children: [
            Expanded(child: Text('${Fa.number(q.progress)} / ${Fa.number(q.target)} ${q.unit}', style: context.text.labelSmall, overflow: TextOverflow.ellipsis)),
            if (q.claimed)
              Text(l.questDone, style: context.text.labelMedium?.copyWith(color: p.green))
            else if (q.claimable)
              AppButton(label: l.questClaim, variant: AppButtonVariant.reward, expand: false, loading: _busy, onPressed: _claim),
          ]),
        ]),
      ),
    );
  }
}

/// Home entry: how many missions are ready to collect.
class QuestsSummaryCard extends ConsumerWidget {
  const QuestsSummaryCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    final quests = ref.watch(questsProvider).value;
    if (quests == null || quests.isEmpty) return const SizedBox.shrink();
    final ready = quests.where((q) => q.claimable).length;
    final done = quests.where((q) => q.claimed).length;
    return Padding(
      padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.md),
      child: AppCard(
        borderColor: ready > 0 ? p.gold : null,
        onTap: () => context.push('/quests'),
        child: Row(children: [
          Icon(Icons.flag_circle_outlined, color: ready > 0 ? p.goldInk : p.green),
          const SizedBox(width: AppSpacing.sm),
          Expanded(child: Text(l.questsTitle, style: context.text.titleSmall)),
          Text(ready > 0 ? l.questsReady(Fa.digits(ready)) : l.questsProgress(Fa.digits(done), Fa.digits(quests.length)),
              style: context.text.labelMedium?.copyWith(color: ready > 0 ? p.goldInk : null)),
          const Icon(Icons.chevron_left_rounded),
        ]),
      ),
    );
  }
}
