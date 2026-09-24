import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../../core/analytics/analytics.dart';
import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/stat_tile.dart';
import '../../../core/widgets/state_views.dart';
import '../../wallet/data/wallet_repository.dart';
import '../data/sponsor_models.dart';
import '../data/sponsor_repository.dart';

class CouponsPage extends StatelessWidget {
  const CouponsPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: Text(l.couponsTitle),
          bottom: TabBar(tabs: [Tab(text: l.couponsMine), Tab(text: l.couponsAvailable)]),
        ),
        body: const TabBarView(children: [_Mine(), _Available()]),
      ),
    );
  }
}

class _Mine extends ConsumerWidget {
  const _Mine();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return RefreshIndicator(
      color: context.palette.green,
      onRefresh: () async => ref.invalidate(myCouponsProvider),
      child: AsyncView(
        value: ref.watch(myCouponsProvider),
        onRetry: () => ref.invalidate(myCouponsProvider),
        data: (items) => items.isEmpty
            ? ListView(children: [EmptyView(title: l.couponsEmpty)])
            : ListView(
                padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
                children: [for (final c in items) Padding(padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.md), child: CouponTicket(coupon: c))],
              ),
      ),
    );
  }
}

/// The code a cashier reads. Used coupons stay visible but muted.
class CouponTicket extends StatelessWidget {
  const CouponTicket({super.key, required this.coupon});

  final UserCouponItem coupon;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final c = coupon;
    return Opacity(
      opacity: c.usable ? 1 : 0.55,
      child: AppCard(
        padding: EdgeInsets.zero,
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Padding(
            padding: const EdgeInsetsDirectional.all(AppSpacing.lg),
            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  if (c.sponsor != null) Text(c.sponsor!.name, style: context.text.labelMedium),
                  Text(c.title, style: context.text.titleSmall),
                  Text(c.discountLabel, style: context.text.bodyMedium?.copyWith(color: p.goldInk, fontWeight: FontWeight.w700)),
                ]),
              ),
              if (!c.usable)
                Container(
                  padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.sm, vertical: 2),
                  decoration: BoxDecoration(color: p.surfaceSunken, borderRadius: BorderRadius.circular(20)),
                  child: Text(c.statusLabel, style: context.text.labelSmall),
                ),
            ]),
          ),
          // Dashed "tear line".
          Row(children: List.generate(40, (i) => Expanded(child: Container(height: 1, color: i.isEven ? p.border : Colors.transparent)))),
          Padding(
            padding: const EdgeInsetsDirectional.all(AppSpacing.lg),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text(l.couponCode, style: context.text.labelSmall),
              InkWell(
                onTap: c.usable
                    ? () async {
                        await Clipboard.setData(ClipboardData(text: c.code));
                        if (context.mounted) showAppSnack(context, l.couponCopied);
                      }
                    : null,
                child: Directionality(
                  textDirection: TextDirection.ltr,
                  child: Text(c.prettyCode, textAlign: TextAlign.center, style: context.text.headlineMedium?.copyWith(letterSpacing: 3, fontFeatures: const [FontFeature.tabularFigures()])),
                ),
              ),
              if (c.usable) Text(l.couponShowCashier, textAlign: TextAlign.center, style: context.text.bodySmall),
              if (c.sharedCode != null && c.usable) Text(l.couponOnlineCode(c.sharedCode!), textAlign: TextAlign.center, style: context.text.bodySmall),
              const SizedBox(height: AppSpacing.sm),
              Text(
                c.usedAt != null ? l.couponUsedAt(FaDate.long(c.usedAt!)) : (c.expiresAt != null ? l.couponExpires(FaDate.long(c.expiresAt!)) : ''),
                textAlign: TextAlign.center,
                style: context.text.labelSmall,
              ),
              if (c.terms != null && c.usable) ...[
                const SizedBox(height: AppSpacing.sm),
                Text('${l.couponTerms}: ${c.terms}', style: context.text.bodySmall?.copyWith(color: p.inkSubtle)),
              ],
            ]),
          ),
        ]),
      ),
    );
  }
}

class _Available extends ConsumerStatefulWidget {
  const _Available();

  @override
  ConsumerState<_Available> createState() => _AvailableState();
}

class _AvailableState extends ConsumerState<_Available> {
  String? _claiming;

  Future<void> _claim(CouponOffer offer) async {
    final l = context.l10n;
    if (offer.pointCost > 0) {
      final ok = await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          title: Text(offer.title),
          content: Text(l.couponClaimConfirm(Fa.number(offer.pointCost))),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.commonCancel)),
            TextButton(onPressed: () => Navigator.pop(c, true), child: Text(l.commonConfirm)),
          ],
        ),
      );
      if (ok != true || !mounted) return;
    }
    setState(() => _claiming = offer.id);
    try {
      // One key per tap: a retried request never charges twice.
      await ref.read(sponsorRepositoryProvider).claim(offer.id, const Uuid().v4());
      ref.read(analyticsProvider).track('coupon_claimed', {'point_cost': offer.pointCost});
      ref.invalidate(myCouponsProvider);
      ref.invalidate(availableCouponsProvider);
      ref.invalidate(walletBalanceProvider);
      if (mounted) {
        showAppSnack(context, l.couponClaimed);
        DefaultTabController.of(context).animateTo(0);
      }
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _claiming = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    return AsyncView(
      value: ref.watch(availableCouponsProvider),
      onRetry: () => ref.invalidate(availableCouponsProvider),
      data: (items) => items.isEmpty
          ? EmptyView(title: l.couponsAvailableEmpty)
          : ListView(
              padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
              children: [
                for (final o in items)
                  Padding(
                    padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.md),
                    child: AppCard(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                        Row(children: [
                          Expanded(
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              if (o.sponsor != null) Text(o.sponsor!.name, style: context.text.labelMedium),
                              Text(o.title, style: context.text.titleSmall),
                              Text(o.discountLabel, style: context.text.bodyMedium?.copyWith(color: p.goldInk)),
                            ]),
                          ),
                          if (o.pointCost > 0) PointsChip(label: Fa.number(o.pointCost)),
                        ]),
                        if (o.description != null) ...[const SizedBox(height: AppSpacing.xs), Text(o.description!, style: context.text.bodySmall)],
                        if (o.remaining != null) Text(l.couponRemaining(Fa.number(o.remaining!)), style: context.text.labelSmall),
                        const SizedBox(height: AppSpacing.md),
                        AppButton.secondary(
                          label: o.pointCost > 0 ? l.couponClaim(Fa.number(o.pointCost)) : l.couponClaimFree,
                          loading: _claiming == o.id,
                          onPressed: _claiming == null ? () => _claim(o) : null,
                        ),
                      ]),
                    ),
                  ),
              ],
            ),
    );
  }
}
