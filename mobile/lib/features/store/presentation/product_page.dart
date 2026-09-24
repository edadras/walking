import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:uuid/uuid.dart';

import '../../../core/analytics/analytics.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/net_image.dart';
import '../../../core/widgets/stat_tile.dart';
import '../../../core/widgets/state_views.dart';
import '../../auth/application/session_controller.dart';
import '../../wallet/data/wallet_repository.dart';
import '../data/store_models.dart';
import '../data/store_repository.dart';
import 'store_page.dart' show ProductImagePlaceholder;

class ProductPage extends ConsumerWidget {
  const ProductPage({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    return Scaffold(
      appBar: AppBar(),
      body: AsyncView(
        value: ref.watch(productProvider(slug)),
        onRetry: () => ref.invalidate(productProvider(slug)),
        data: (pr) {
          final level = ref.watch(meProvider).level;
          final locked = level < pr.minLevel;
          return Column(children: [
            Expanded(
              child: ListView(children: [
                ProductGallery(slug: pr.slug, type: pr.type, images: pr.images.isNotEmpty ? pr.images : [?pr.imageUrl]),
                Padding(
                  padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text([pr.typeLabel, ?pr.sponsor].join(' · '), style: context.text.labelMedium),
                    const SizedBox(height: AppSpacing.xs),
                    Text(pr.name, style: context.text.headlineSmall),
                    const SizedBox(height: AppSpacing.sm),
                    PointsChip(label: Fa.number(pr.pointPrice), large: true),
                    const SizedBox(height: AppSpacing.md),
                    if (pr.summary != null) Text(pr.summary!, style: context.text.bodyLarge),
                    if (pr.stock != null && pr.inStock) Text(l.storeFewLeft(Fa.digits(pr.stock!)), style: context.text.bodySmall?.copyWith(color: p.danger)),
                    if (pr.maxPerUser != null) Text(l.storeMaxPerUser(Fa.digits(pr.maxPerUser!)), style: context.text.bodySmall),
                    if (pr.instant) Text(l.checkoutInstant, style: context.text.bodySmall),
                    if (pr.plainDescription.isNotEmpty) ...[
                      const SizedBox(height: AppSpacing.lg),
                      Text(pr.plainDescription, style: context.text.bodyMedium),
                    ],
                  ]),
                ),
              ]),
            ),
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, AppSpacing.sm, AppSpacing.gutter, AppSpacing.md),
                child: AppButton(
                  label: !pr.inStock ? l.storeOutOfStock : (locked ? l.storeMinLevel(Fa.digits(pr.minLevel)) : l.storeBuy),
                  icon: Icons.shopping_bag_outlined,
                  onPressed: pr.inStock && !locked ? () => showCheckout(context, pr) : null,
                ),
              ),
            ),
          ]);
        },
      ),
    );
  }
}

Future<void> showCheckout(BuildContext context, Product product) => showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => CheckoutSheet(product: product),
    );

class CheckoutSheet extends ConsumerStatefulWidget {
  const CheckoutSheet({super.key, required this.product});

  final Product product;

  @override
  ConsumerState<CheckoutSheet> createState() => _CheckoutSheetState();
}

class _CheckoutSheetState extends ConsumerState<CheckoutSheet> {
  // One key for this checkout: a double tap or a retry after a timeout never buys twice.
  final _key = const Uuid().v4();
  int _qty = 1;
  String? _addressId;
  bool _busy = false;

  Future<void> _pay() async {
    setState(() => _busy = true);
    try {
      final order = await ref.read(storeRepositoryProvider).placeOrder(productId: widget.product.id, quantity: _qty, idempotencyKey: _key, addressId: _addressId);
      ref.read(analyticsProvider).track('purchase', {'product_type': widget.product.type, 'quantity': _qty, 'points': order.totalPoints});
      ref.invalidate(walletBalanceProvider);
      ref.invalidate(ordersProvider);
      if (!mounted) return;
      final router = GoRouter.maybeOf(context);
      Navigator.of(context).pop();
      router?.push('/orders/${order.id}');
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
    final pr = widget.product;
    final balance = ref.watch(walletBalanceProvider).value?.available;
    final total = pr.pointPrice * _qty;
    final short = balance != null && balance < total;

    return Padding(
      padding: EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, 0, AppSpacing.gutter, AppSpacing.xl + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text(l.checkoutTitle, style: context.text.titleLarge),
        const SizedBox(height: AppSpacing.md),
        Text(pr.name, style: context.text.titleSmall),
        if (pr.maxQuantity > 1) ...[
          const SizedBox(height: AppSpacing.md),
          Row(children: [
            Text(l.checkoutQuantity, style: context.text.bodyLarge),
            const Spacer(),
            IconButton.outlined(icon: const Icon(Icons.remove), onPressed: _qty > 1 ? () => setState(() => _qty--) : null),
            SizedBox(width: 40, child: Text(Fa.digits(_qty), textAlign: TextAlign.center, style: context.text.titleMedium)),
            IconButton.outlined(icon: const Icon(Icons.add), onPressed: _qty < pr.maxQuantity ? () => setState(() => _qty++) : null),
          ]),
        ],
        if (pr.needsAddress) ...[
          const SizedBox(height: AppSpacing.md),
          Text(l.checkoutAddress, style: context.text.labelMedium),
          ref.watch(addressesProvider).when(
                data: (list) {
                  _addressId ??= list.where((a) => a.isDefault).firstOrNull?.id ?? list.firstOrNull?.id;
                  return Column(children: [
                    RadioGroup<String>(
                      groupValue: _addressId,
                      onChanged: (v) => setState(() => _addressId = v),
                      child: Column(children: [
                        for (final a in list)
                          RadioListTile<String>(
                            contentPadding: EdgeInsets.zero,
                            value: a.id,
                            title: Text(a.title ?? a.recipient, style: context.text.bodyLarge),
                            subtitle: Text(a.oneLine, maxLines: 2, overflow: TextOverflow.ellipsis),
                          ),
                      ]),
                    ),
                    Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: TextButton.icon(
                        icon: const Icon(Icons.add_location_alt_outlined),
                        label: Text(l.checkoutAddAddress),
                        onPressed: () async {
                          await context.push('/addresses/new');
                          ref.invalidate(addressesProvider);
                        },
                      ),
                    ),
                  ]);
                },
                loading: () => const Padding(padding: EdgeInsets.all(AppSpacing.md), child: LinearProgressIndicator()),
                error: (e, _) => ErrorView(error: e, compact: true, onRetry: () => ref.invalidate(addressesProvider)),
              ),
        ],
        const Divider(height: AppSpacing.xxl),
        Row(children: [
          Text(l.checkoutTotal, style: context.text.titleMedium),
          const Spacer(),
          PointsChip(label: Fa.number(total), large: true),
        ]),
        if (balance != null) ...[
          const SizedBox(height: AppSpacing.xs),
          Text(short ? l.storeNeedMore(Fa.number(total - balance)) : l.checkoutAfter(Fa.number(balance - total)),
              style: context.text.bodySmall?.copyWith(color: short ? p.danger : null)),
        ],
        const SizedBox(height: AppSpacing.lg),
        AppButton(
          label: l.checkoutConfirm(Fa.number(total)),
          loading: _busy,
          onPressed: short || (pr.needsAddress && _addressId == null) ? null : _pay,
        ),
      ]),
    );
  }
}

/// Swipeable 6:5 gallery; the first image continues the store card's Hero.
class ProductGallery extends StatefulWidget {
  const ProductGallery({super.key, required this.slug, required this.type, required this.images});

  final String slug;
  final String type;
  final List<String> images;

  @override
  State<ProductGallery> createState() => _ProductGalleryState();
}

class _ProductGalleryState extends State<ProductGallery> {
  int _page = 0;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final images = widget.images;
    final placeholder = ProductImagePlaceholder(type: widget.type);
    return AspectRatio(
      aspectRatio: 1.2,
      child: images.isEmpty
          ? Hero(tag: 'product-${widget.slug}', child: placeholder)
          : Stack(children: [
              PageView.builder(
                itemCount: images.length,
                onPageChanged: (i) => setState(() => _page = i),
                itemBuilder: (_, i) {
                  final image = Semantics(
                    image: true,
                    label: images.length > 1 ? context.l10n.productGalleryLabel(Fa.digits(i + 1), Fa.digits(images.length)) : null,
                    child: NetImage(images[i], fallback: placeholder),
                  );
                  return i == 0 ? Hero(tag: 'product-${widget.slug}', child: image) : image;
                },
              ),
              if (images.length > 1)
                PositionedDirectional(
                  bottom: AppSpacing.md,
                  start: 0,
                  end: 0,
                  child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    for (var i = 0; i < images.length; i++)
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        margin: const EdgeInsets.symmetric(horizontal: 3),
                        width: i == _page ? 18 : 7,
                        height: 7,
                        decoration: BoxDecoration(
                          color: i == _page ? p.green : Colors.white.withValues(alpha: 0.85),
                          borderRadius: BorderRadius.circular(4),
                          boxShadow: const [BoxShadow(color: Color(0x33000000), blurRadius: 3)],
                        ),
                      ),
                  ]),
                ),
            ]),
    );
  }
}
