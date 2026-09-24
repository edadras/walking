import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/skeleton.dart';
import '../../../core/widgets/state_views.dart';
import '../../../core/widgets/trail.dart';
import '../data/wallet_models.dart';
import '../data/wallet_repository.dart';

class WalletPage extends ConsumerStatefulWidget {
  const WalletPage({super.key});

  @override
  ConsumerState<WalletPage> createState() => _WalletPageState();
}

class _WalletPageState extends ConsumerState<WalletPage> {
  String _filter = 'all';

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final balance = ref.watch(walletBalanceProvider);
    final txs = ref.watch(txListProvider(_filter));

    final filters = {
      'all': l.walletFilterAll,
      'earned': l.walletFilterEarned,
      'spent': l.walletFilterSpent,
      'reward': l.walletFilterReward,
      'purchase': l.walletFilterPurchase,
      'sponsor': l.walletFilterSponsor,
      'adjustment': l.walletFilterAdjustment,
    };

    return Scaffold(
      appBar: AppBar(title: Text(l.walletTitle)),
      body: RefreshIndicator(
        color: context.palette.green,
        onRefresh: () async {
          ref.invalidate(walletBalanceProvider);
          ref.invalidate(txListProvider(_filter));
        },
        child: NotificationListener<ScrollNotification>(
          onNotification: (n) {
            if (n.metrics.pixels > n.metrics.maxScrollExtent - 300) ref.read(txListProvider(_filter).notifier).loadMore();
            return false;
          },
          child: ListView(
            padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter, vertical: AppSpacing.sm),
            children: [
              AsyncView(
                value: balance,
                onRetry: () => ref.invalidate(walletBalanceProvider),
                skeleton: const Shimmer(child: SkeletonBox(height: 176, radius: AppRadius.md)),
                data: (b) => _BalanceCard(balance: b),
              ),
              SectionHeader(title: l.walletHistory),
              SizedBox(
                height: 40,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  children: [
                    for (final e in filters.entries)
                      Padding(
                        padding: const EdgeInsetsDirectional.only(end: AppSpacing.sm),
                        child: ChoiceChip(
                          label: Text(e.value),
                          selected: _filter == e.key,
                          showCheckmark: false,
                          onSelected: (_) => setState(() => _filter = e.key),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              AsyncView(
                value: txs,
                onRetry: () => ref.invalidate(txListProvider(_filter)),
                data: (state) => state.items.isEmpty
                    ? EmptyView(title: l.walletEmpty, message: l.walletEmptyBody, compact: true)
                    : Column(children: [
                        for (final t in state.items) _TxRow(tx: t),
                        if (state.loadingMore) const Padding(padding: EdgeInsets.all(AppSpacing.lg), child: TrailLoader()),
                      ]),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BalanceCard extends StatelessWidget {
  const _BalanceCard({required this.balance});

  final WalletBalance balance;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    return AppCard(
      padding: const EdgeInsetsDirectional.all(AppSpacing.xl),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l.walletAvailable, style: context.text.labelMedium),
          const SizedBox(height: AppSpacing.xs),
          Row(crossAxisAlignment: CrossAxisAlignment.baseline, textBaseline: TextBaseline.alphabetic, children: [
            Container(width: 10, height: 10, decoration: BoxDecoration(color: p.gold, shape: BoxShape.circle)),
            const SizedBox(width: AppSpacing.sm),
            Text(Fa.number(balance.available), style: context.text.displaySmall),
            const SizedBox(width: AppSpacing.xs),
            Text(l.pointsUnit, style: context.text.bodyMedium?.copyWith(color: p.inkMuted)),
          ]),
          const SizedBox(height: AppSpacing.xs),
          Text(l.walletValue(Fa.rial(balance.rialValue)), style: context.text.bodyMedium?.copyWith(color: p.goldInk, fontWeight: FontWeight.w600)),
          const Padding(padding: EdgeInsets.symmetric(vertical: AppSpacing.lg), child: Divider()),
          Row(children: [
            Expanded(child: _Mini(label: l.walletPending, value: Fa.number(balance.pending))),
            Expanded(child: _Mini(label: l.walletLifetime, value: Fa.number(balance.lifetimeEarned))),
            Expanded(child: _Mini(label: l.walletSpent, value: Fa.number(balance.lifetimeSpent))),
          ]),
          if (balance.pending > 0) ...[
            const SizedBox(height: AppSpacing.md),
            Text(
              balance.nextReleaseAt == null ? l.walletPendingHint : '${l.walletPendingHint} ${l.walletNextRelease(FaDate.relativeFuture(balance.nextReleaseAt!))}',
              style: context.text.bodySmall,
            ),
          ],
          const SizedBox(height: AppSpacing.sm),
          Text(l.walletRate(Fa.rial(balance.rialPerPoint)), style: context.text.labelSmall),
        ],
      ),
    );
  }
}

class _Mini extends StatelessWidget {
  const _Mini({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(value, style: context.text.titleSmall),
        Text(label, style: context.text.labelSmall),
      ]);
}

class _TxRow extends StatelessWidget {
  const _TxRow({required this.tx});

  final PointTx tx;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final l = context.l10n;
    final positive = tx.amount > 0;
    final color = tx.isReversed ? p.inkSubtle : (positive ? p.green : p.danger);
    return Padding(
      padding: const EdgeInsetsDirectional.symmetric(vertical: AppSpacing.md),
      child: Row(children: [
        Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(color: positive ? p.greenSoft : p.dangerSoft, shape: BoxShape.circle),
          child: Icon(positive ? Icons.south_west_rounded : Icons.north_east_rounded, size: 18, color: positive ? p.green : p.danger),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(tx.description.isEmpty ? tx.typeLabel : tx.description, style: context.text.bodyLarge, maxLines: 1, overflow: TextOverflow.ellipsis),
            Text(
              [FaDate.relative(tx.createdAt), if (tx.isPending) l.walletPending, if (tx.isReversed) l.walletReversed].join(' · '),
              style: context.text.bodySmall,
            ),
          ]),
        ),
        Text(
          Fa.signedPoints(tx.amount),
          style: context.text.titleSmall?.copyWith(
            color: color,
            decoration: tx.isReversed ? TextDecoration.lineThrough : null,
            fontFeatures: const [FontFeature.tabularFigures()],
          ),
        ),
      ]),
    );
  }
}
