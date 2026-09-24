import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/analytics/analytics.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/stat_tile.dart';
import '../../../core/widgets/state_views.dart';
import '../../config/data/app_config.dart';
import '../../wallet/data/wallet_repository.dart';
import '../data/store_models.dart';
import '../data/store_repository.dart';
import '../../../core/widgets/net_image.dart';

class StorePage extends ConsumerStatefulWidget {
  const StorePage({super.key});

  @override
  ConsumerState<StorePage> createState() => _StorePageState();
}

class _StorePageState extends ConsumerState<StorePage> {
  String? _category;
  String _q = '';
  String _sort = 'featured';
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    ref.read(analyticsProvider).track('store_view');
  }

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final enabled = ref.watch(configProvider).feature('store');
    final balance = ref.watch(walletBalanceProvider).value;
    final query = (category: _category, q: _q, sort: _sort);

    return Scaffold(
      appBar: AppBar(
        title: Text(l.storeTitle),
        actions: [
          if (balance != null) Padding(padding: const EdgeInsetsDirectional.only(end: AppSpacing.sm), child: Center(child: PointsChip(label: Fa.number(balance.available)))),
          IconButton(tooltip: l.ordersTitle, icon: const Icon(Icons.receipt_long_outlined), onPressed: () => context.push('/orders')),
        ],
      ),
      body: !enabled
          ? EmptyView(title: l.storeDisabled)
          : RefreshIndicator(
              color: p.green,
              onRefresh: () async {
                ref.invalidate(productsProvider(query));
                ref.invalidate(walletBalanceProvider);
              },
              child: CustomScrollView(slivers: [
                SliverPadding(
                  padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, AppSpacing.sm, AppSpacing.gutter, 0),
                  sliver: SliverToBoxAdapter(
                    child: Row(children: [
                      Expanded(
                        child: TextField(
                          decoration: InputDecoration(hintText: l.storeSearch, prefixIcon: const Icon(Icons.search_rounded), isDense: true),
                          onChanged: (v) {
                            _debounce?.cancel();
                            _debounce = Timer(const Duration(milliseconds: 400), () => setState(() => _q = v.trim()));
                          },
                        ),
                      ),
                      PopupMenuButton<String>(
                        icon: const Icon(Icons.sort_rounded),
                        initialValue: _sort,
                        onSelected: (v) => setState(() => _sort = v),
                        itemBuilder: (_) => [
                          PopupMenuItem(value: 'featured', child: Text(l.storeSortFeatured)),
                          PopupMenuItem(value: 'price_asc', child: Text(l.storeSortCheap)),
                          PopupMenuItem(value: 'price_desc', child: Text(l.storeSortExpensive)),
                          PopupMenuItem(value: 'newest', child: Text(l.storeSortNew)),
                        ],
                      ),
                    ]),
                  ),
                ),
                SliverToBoxAdapter(
                  child: SizedBox(
                    height: 56,
                    child: ref.watch(storeCategoriesProvider).when(
                          data: (cats) => ListView(
                            scrollDirection: Axis.horizontal,
                            padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter, vertical: AppSpacing.sm),
                            children: [
                              for (final c in [null, ...cats.where((c) => c.parent == null)])
                                Padding(
                                  padding: const EdgeInsetsDirectional.only(end: AppSpacing.sm),
                                  child: ChoiceChip(
                                    label: Text(c?.name ?? l.storeAll),
                                    selected: _category == c?.id,
                                    showCheckmark: false,
                                    onSelected: (_) => setState(() => _category = c?.id),
                                  ),
                                ),
                            ],
                          ),
                          loading: () => const SizedBox.shrink(),
                          error: (_, _) => const SizedBox.shrink(),
                        ),
                  ),
                ),
                ref.watch(productsProvider(query)).when(
                      data: (items) => items.isEmpty
                          ? SliverFillRemaining(hasScrollBody: false, child: EmptyView(title: l.storeEmpty))
                          : SliverPadding(
                              padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, 0, AppSpacing.gutter, AppSpacing.xxl),
                              sliver: SliverGrid.builder(
                                gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(maxCrossAxisExtent: 220, mainAxisSpacing: AppSpacing.md, crossAxisSpacing: AppSpacing.md, childAspectRatio: 0.68),
                                itemCount: items.length,
                                itemBuilder: (_, i) => ProductCard(product: items[i]),
                              ),
                            ),
                      loading: () => const SliverFillRemaining(child: LoadingView()),
                      error: (e, _) => SliverFillRemaining(child: ErrorView(error: e, onRetry: () => ref.invalidate(productsProvider(query)))),
                    ),
              ]),
            ),
    );
  }
}

class ProductCard extends StatelessWidget {
  const ProductCard({super.key, required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final pr = product;
    return AppCard(
      padding: EdgeInsets.zero,
      onTap: () {
        trackFrom(context, 'product_view', {'product': pr.slug, 'product_type': pr.type});
        context.push('/store/${pr.slug}');
      },
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Expanded(
          child: ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(AppRadius.md)),
            child: Hero(tag: 'product-${pr.slug}', child: NetImage(pr.imageUrl, fallback: ProductImagePlaceholder(type: pr.type))),
          ),
        ),
        Padding(
          padding: const EdgeInsetsDirectional.all(AppSpacing.md),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(pr.name, style: context.text.titleSmall, maxLines: 2, overflow: TextOverflow.ellipsis),
            const SizedBox(height: AppSpacing.xs),
            Row(children: [
              PointsChip(label: Fa.number(pr.pointPrice)),
              const SizedBox(width: AppSpacing.xs),
              Expanded(
                child: Text(
                  !pr.inStock ? l.storeOutOfStock : (pr.minLevel > 1 ? l.storeMinLevel(Fa.digits(pr.minLevel)) : ''),
                  textAlign: TextAlign.end,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: context.text.labelSmall?.copyWith(color: pr.inStock ? null : p.danger),
                ),
              ),
            ]),
          ]),
        ),
      ]),
    );
  }
}

class ProductImagePlaceholder extends StatelessWidget {
  const ProductImagePlaceholder({super.key, required this.type});

  final String type;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return ColoredBox(
      color: p.greenSoft,
      child: Icon(
        switch (type) {
          'digital_code' => Icons.card_giftcard_rounded,
          'coupon' => Icons.confirmation_number_outlined,
          'service' => Icons.volunteer_activism_outlined,
          _ => Icons.inventory_2_outlined,
        },
        size: 40,
        color: p.green,
      ),
    );
  }
}
