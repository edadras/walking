import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/analytics/analytics.dart';
import '../../../core/format/dates.dart';
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
import '../../activity/application/activity_providers.dart';
import '../data/challenge_models.dart';
import '../data/challenge_repository.dart';
import '../../../core/widgets/net_image.dart';

String metricTarget(BuildContext context, ChallengeItem c, int value) =>
    c.metric == 'distance_m' ? '${Fa.decimal(value / 1000)} ${context.l10n.unitKm}' : '${Fa.number(value)} ${context.l10n.unitSteps}';

class ChallengesPage extends ConsumerWidget {
  const ChallengesPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.challengesTitle)),
      body: RefreshIndicator(
        color: context.palette.green,
        onRefresh: () async => ref.invalidate(challengesProvider),
        child: AsyncView(
          value: ref.watch(challengesProvider),
          onRetry: () => ref.invalidate(challengesProvider),
          data: (items) {
            if (items.isEmpty) return ListView(children: [EmptyView(title: l.challengesEmpty)]);
            final groups = [
              (l.challengesRunning, items.where((c) => c.status == 'running').toList()),
              (l.challengesUpcoming, items.where((c) => c.status == 'upcoming').toList()),
              (l.challengesEnded, items.where((c) => c.status == 'ended').toList()),
            ];
            return ListView(
              padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter, vertical: AppSpacing.sm),
              children: [
                for (final (title, list) in groups)
                  if (list.isNotEmpty) ...[
                    SectionHeader(title: title),
                    for (final c in list) Padding(padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.md), child: ChallengeCard(item: c)),
                  ],
              ],
            );
          },
        ),
      ),
    );
  }
}

class ChallengeCard extends StatelessWidget {
  const ChallengeCard({super.key, required this.item});

  final ChallengeItem item;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final c = item;
    return AppCard(
      onTap: () => context.push('/challenges/${c.id}'),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text(c.title, style: context.text.titleSmall)),
          if (c.sponsored) _Tag(label: l.challengeSponsored, color: p.goldSoft, ink: p.goldInk),
          if (c.completed) _Tag(label: l.challengeCompleted, color: p.greenSoft, ink: p.greenStrong)
          else if (c.joined) _Tag(label: l.challengeJoined, color: p.greenSoft, ink: p.greenStrong),
        ]),
        const SizedBox(height: AppSpacing.xxs),
        Text(
          [c.typeLabel, l.challengeTarget(metricTarget(context, c, c.target)), l.challengeParticipants(Fa.number(c.participants))].join(' · '),
          style: context.text.bodySmall,
        ),
        if (c.joined) ...[
          const SizedBox(height: AppSpacing.sm),
          ClipRRect(
            borderRadius: BorderRadius.circular(2),
            child: LinearProgressIndicator(value: c.fraction, minHeight: 4, color: p.green, backgroundColor: p.surfaceSunken),
          ),
        ],
        const SizedBox(height: AppSpacing.sm),
        Row(children: [
          Text(
            c.status == 'upcoming' ? FaDate.dayMonth(c.startsAt) : l.challengeEnds(FaDate.relativeFuture(c.endsAt)),
            style: context.text.labelSmall,
          ),
          const Spacer(),
          if (c.rewardPoints > 0) PointsChip(label: '+${Fa.number(c.rewardPoints)}'),
        ]),
      ]),
    );
  }
}

class _Tag extends StatelessWidget {
  const _Tag({required this.label, required this.color, required this.ink});

  final String label;
  final Color color;
  final Color ink;

  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsetsDirectional.only(start: AppSpacing.xs),
        padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.sm, vertical: 2),
        decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(20)),
        child: Text(label, style: context.text.labelSmall?.copyWith(color: ink)),
      );
}

class ChallengeDetailPage extends ConsumerStatefulWidget {
  const ChallengeDetailPage({super.key, required this.id});

  final String id;

  @override
  ConsumerState<ChallengeDetailPage> createState() => _ChallengeDetailPageState();
}

class _ChallengeDetailPageState extends ConsumerState<ChallengeDetailPage> {
  bool _joining = false;

  Future<void> _join() async {
    setState(() => _joining = true);
    try {
      await joinChallenge(ref.read(apiClientProvider), widget.id);
      ref.read(analyticsProvider).track('challenge_joined');
      ref.invalidate(challengeProvider(widget.id));
      ref.invalidate(challengesProvider);
      ref.invalidate(homeProvider);
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _joining = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    return Scaffold(
      appBar: AppBar(title: Text(l.challengesTitle)),
      body: AsyncView(
        value: ref.watch(challengeProvider(widget.id)),
        onRetry: () => ref.invalidate(challengeProvider(widget.id)),
        data: (c) => ListView(
          padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
          children: [
            if (c.imageUrl != null) ...[
              ClipRRect(borderRadius: AppRadius.mdAll, child: AspectRatio(aspectRatio: 16 / 9, child: NetImage(c.imageUrl))),
              const SizedBox(height: AppSpacing.lg),
            ],
            Text(c.title, style: context.text.headlineSmall),
            const SizedBox(height: AppSpacing.xs),
            Text([c.typeLabel, if (c.sponsored) l.challengeSponsored, l.challengeParticipants(Fa.number(c.participants))].join(' · '), style: context.text.bodySmall),
            if (c.description != null && c.description!.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.md),
              Text(c.description!, style: context.text.bodyMedium),
            ],
            const SizedBox(height: AppSpacing.lg),
            AppCard(
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Row(children: [
                  Expanded(child: Text(l.challengeTarget(metricTarget(context, c, c.target)), style: context.text.titleSmall)),
                  Text(c.status == 'upcoming' ? FaDate.long(c.startsAt) : l.challengeEnds(FaDate.long(c.endsAt)), style: context.text.labelSmall),
                ]),
                if (c.joined) ...[
                  const SizedBox(height: AppSpacing.md),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(3),
                    child: LinearProgressIndicator(value: c.fraction, minHeight: 8, color: c.completed ? p.gold : p.green, backgroundColor: p.surfaceSunken),
                  ),
                  const SizedBox(height: AppSpacing.xs),
                  Text('${metricTarget(context, c, c.progress)} · ${Fa.percent(c.fraction * 100)}', style: context.text.bodySmall),
                ],
              ]),
            ),
            const SizedBox(height: AppSpacing.md),
            AppCard(
              child: Row(children: [
                Icon(Icons.card_giftcard_rounded, color: p.goldInk),
                const SizedBox(width: AppSpacing.sm),
                Expanded(child: Text(l.challengeReward, style: context.text.bodyMedium)),
                if (c.rewardPoints > 0) PointsChip(label: '+${Fa.number(c.rewardPoints)}'),
                if (c.rewardXp > 0) ...[const SizedBox(width: AppSpacing.xs), Text('+${Fa.number(c.rewardXp)} XP', style: context.text.labelSmall)],
              ]),
            ),
            const SizedBox(height: AppSpacing.lg),
            if (c.completed)
              AppCard(color: p.goldSoft, borderColor: p.goldSoft, child: Text(l.challengeCompleted, style: context.text.titleSmall?.copyWith(color: p.goldInk)))
            else if (c.joinable && !c.joined)
              AppButton(label: l.challengeJoin, icon: Icons.flag_rounded, loading: _joining, onPressed: _join),
            const SizedBox(height: AppSpacing.sm),
            Text(l.challengeProgressOnlyAfterJoin, style: context.text.bodySmall?.copyWith(color: p.inkSubtle)),
            if (c.top.isNotEmpty) ...[
              SectionHeader(title: l.challengeTop),
              for (final t in c.top)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  tileColor: t.isMe ? p.greenSoft : null,
                  leading: SizedBox(width: 28, child: Text(Fa.digits(t.rank), style: context.text.titleSmall, textAlign: TextAlign.center)),
                  title: Text(t.isMe ? '${t.name} (${l.lbYou})' : t.name),
                  trailing: t.completed ? Icon(Icons.check_circle_rounded, color: p.green) : Text(metricTarget(context, c, t.progress), style: context.text.bodySmall),
                ),
            ],
          ],
        ),
      ),
    );
  }
}
