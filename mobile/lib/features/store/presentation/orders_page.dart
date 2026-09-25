import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/platform/external_url.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/net_image.dart';
import '../../../core/widgets/stat_tile.dart';
import '../../../core/widgets/state_views.dart';
import '../../wallet/data/wallet_repository.dart';
import '../data/store_models.dart';
import '../data/store_repository.dart';
import 'store_page.dart' show ProductImagePlaceholder;

Color _statusColor(BuildContext context, String status) {
  final p = context.palette;
  return switch (status) {
    'delivered' => p.green,
    'refunded' || 'cancelled' => p.danger,
    'shipped' => p.info,
    _ => p.goldInk,
  };
}

class OrdersPage extends ConsumerWidget {
  const OrdersPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.ordersTitle)),
      body: RefreshIndicator(
        color: context.palette.green,
        onRefresh: () async => ref.invalidate(ordersProvider),
        child: AsyncView(
          value: ref.watch(ordersProvider),
          onRetry: () => ref.invalidate(ordersProvider),
          data: (orders) => orders.isEmpty
              ? ListView(children: [EmptyView(title: l.ordersEmpty)])
              : ListView.separated(
                  padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
                  itemCount: orders.length,
                  separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
                  itemBuilder: (_, i) {
                    final o = orders[i];
                    return AppCard(
                      onTap: () => context.push('/orders/${o.id}'),
                      child: Row(children: [
                        _Thumb(url: o.imageUrl),
                        const SizedBox(width: AppSpacing.md),
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(o.title ?? o.number, style: context.text.titleSmall, maxLines: 1, overflow: TextOverflow.ellipsis),
                            Text('${o.number} · ${FaDate.long(o.placedAt)} · ${l.orderItems(Fa.digits(o.itemCount))}', style: context.text.bodySmall),
                            const SizedBox(height: AppSpacing.xs),
                            Text(o.statusLabel, style: context.text.labelMedium?.copyWith(color: _statusColor(context, o.status))),
                          ]),
                        ),
                        if (o.isMoney) Text(Fa.rial(o.totalRial), style: context.text.labelLarge) else PointsChip(label: Fa.number(o.totalPoints)),
                      ]),
                    );
                  },
                ),
        ),
      ),
    );
  }
}

class OrderDetailPage extends ConsumerStatefulWidget {
  const OrderDetailPage({super.key, required this.id});

  final String id;

  @override
  ConsumerState<OrderDetailPage> createState() => _OrderDetailPageState();
}

class _OrderDetailPageState extends ConsumerState<OrderDetailPage> {
  bool _cancelling = false;

  // Back from the bank's page: show the verified result straight away.
  late final _lifecycle = AppLifecycleListener(onResume: () => ref.invalidate(orderProvider(widget.id)));

  @override
  void initState() {
    super.initState();
    _lifecycle;
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    super.dispose();
  }

  Future<void> _cancel() async {
    final l = context.l10n;
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        content: Text(l.orderCancelConfirm),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.commonCancel)),
          TextButton(onPressed: () => Navigator.pop(c, true), child: Text(l.commonConfirm)),
        ],
      ),
    );
    if (ok != true) return;
    setState(() => _cancelling = true);
    try {
      await ref.read(storeRepositoryProvider).cancel(widget.id);
      ref.invalidate(orderProvider(widget.id));
      ref.invalidate(ordersProvider);
      ref.invalidate(walletBalanceProvider);
      if (mounted) showAppSnack(context, l.orderCancelled);
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _cancelling = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    return Scaffold(
      appBar: AppBar(title: Text(l.ordersTitle)),
      body: AsyncView(
        value: ref.watch(orderProvider(widget.id)),
        onRetry: () => ref.invalidate(orderProvider(widget.id)),
        data: (o) => ListView(
          padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
          children: [
            Row(children: [
              Expanded(child: Text(o.number, style: context.text.titleMedium)),
              Text(o.statusLabel, style: context.text.titleSmall?.copyWith(color: _statusColor(context, o.status))),
            ]),
            Text(FaDate.long(o.placedAt), style: context.text.bodySmall),
            if (o.isMoney && o.payment != null) ...[
              const SizedBox(height: AppSpacing.md),
              _PaymentCard(order: o, payment: o.payment!),
            ],
            if (o.trackingCode != null) ...[
              const SizedBox(height: AppSpacing.xs),
              SelectableText(l.orderTracking(o.trackingCode!), style: context.text.bodyMedium),
            ],
            const SizedBox(height: AppSpacing.lg),
            for (final item in o.items) ...[
              AppCard(
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Row(children: [
                    _Thumb(url: item.imageUrl, type: item.type),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(child: Text(item.name, style: context.text.titleSmall)),
                    Text('${Fa.digits(item.quantity)} × ${Fa.number(item.unitPointPrice)}', style: context.text.bodySmall),
                  ]),
                  if (item.codes.isNotEmpty) ...[
                    const SizedBox(height: AppSpacing.md),
                    Text(l.orderCodes, style: context.text.labelMedium),
                    for (final code in item.codes)
                      Container(
                        margin: const EdgeInsetsDirectional.only(top: AppSpacing.xs),
                        decoration: BoxDecoration(color: p.surfaceSunken, borderRadius: AppRadius.smAll),
                        child: ListTile(
                          dense: true,
                          title: Directionality(textDirection: TextDirection.ltr, child: SelectableText(code, style: context.text.titleSmall?.copyWith(letterSpacing: 1.5))),
                          trailing: IconButton(
                            icon: const Icon(Icons.copy_rounded),
                            tooltip: l.orderCodeCopied,
                            onPressed: () async {
                              await Clipboard.setData(ClipboardData(text: code));
                              if (context.mounted) showAppSnack(context, l.orderCodeCopied);
                            },
                          ),
                        ),
                      ),
                  ],
                  if (item.couponId != null)
                    Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: TextButton(onPressed: () => context.push('/coupons'), child: Text(l.orderCouponLink)),
                    ),
                ]),
              ),
              const SizedBox(height: AppSpacing.sm),
            ],
            AppCard(
              child: Row(children: [
                Text(l.checkoutTotal, style: context.text.titleSmall),
                const Spacer(),
                if (o.isMoney) Text(Fa.rial(o.totalRial), style: context.text.titleSmall) else PointsChip(label: Fa.number(o.totalPoints)),
              ]),
            ),
            if (o.shippingAddress != null) ...[
              SectionHeader(title: l.orderShipTo),
              Text('${o.shippingAddress!.recipient} · ${Fa.digits(o.shippingAddress!.phone)}', style: context.text.bodyMedium),
              Text(o.shippingAddress!.oneLine, style: context.text.bodySmall),
            ],
            SectionHeader(title: l.orderTimeline),
            for (final (i, h) in o.history.indexed)
              Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Column(children: [
                  Container(width: 12, height: 12, decoration: BoxDecoration(color: i == o.history.length - 1 ? p.green : p.border, shape: BoxShape.circle)),
                  if (i < o.history.length - 1) Container(width: 2, height: 34, color: p.border),
                ]),
                const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(h.label, style: context.text.bodyMedium),
                    Text([FaDate.relative(h.at), ?h.note].join(' · '), style: context.text.bodySmall),
                  ]),
                ),
              ]),
            if (o.cancellable) ...[
              const SizedBox(height: AppSpacing.xl),
              AppButton.secondary(label: l.orderCancel, loading: _cancelling, onPressed: _cancel),
            ],
          ],
        ),
      ),
    );
  }
}

class _Thumb extends StatelessWidget {
  const _Thumb({required this.url, this.type = 'physical'});

  final String? url;
  final String type;

  @override
  Widget build(BuildContext context) => ClipRRect(
        borderRadius: AppRadius.smAll,
        child: SizedBox.square(
          dimension: 52,
          child: NetImage(url, fallback: FittedBox(child: SizedBox.square(dimension: 96, child: ProductImagePlaceholder(type: type)))),
        ),
      );
}

class _PaymentCard extends ConsumerWidget {
  const _PaymentCard({required this.order, required this.payment});

  final Order order;
  final OrderPayment payment;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    final (icon, color, text) = switch (payment.status) {
      'paid' => (Icons.verified_rounded, p.green, l.orderPaidRef(payment.refId ?? '—')),
      'pending' => (Icons.schedule_rounded, p.goldInk, payment.payUrl != null ? l.orderAwaitingPayment : l.orderPaymentExpired),
      _ => (Icons.cancel_outlined, p.danger, l.orderPaymentFailed),
    };
    return AppCard(
      borderColor: color,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(icon, color: color),
          const SizedBox(width: AppSpacing.sm),
          Expanded(child: SelectableText(text, style: context.text.bodyMedium)),
        ]),
        if (order.awaitingPayment && payment.payUrl != null) ...[
          const SizedBox(height: AppSpacing.md),
          AppButton(
            label: l.orderPayNow,
            icon: Icons.credit_card_rounded,
            onPressed: () => ref.read(externalUrlOpenerProvider)(payment.payUrl!),
          ),
        ],
      ]),
    );
  }
}
