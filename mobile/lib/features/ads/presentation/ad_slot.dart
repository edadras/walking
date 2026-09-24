import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_card.dart';
import '../application/ad_providers.dart';
import '../data/ads_repository.dart';
import '../../../core/widgets/net_image.dart';

/// Opens an ad destination: in-app routes stay in the app, https opens outside.
Future<void> openAdAction(BuildContext context, String? url) async {
  if (url == null || url.isEmpty) return;
  if (url.startsWith('/')) {
    context.push(url);
  } else if (url.startsWith('https://')) {
    await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
  }
}

/// A banner or native ad for [placement]. Collapses to nothing when there is
/// no ad (or ads are off), so screens never show empty frames.
class AdSlot extends ConsumerWidget {
  const AdSlot({super.key, required this.placement, this.padding = const EdgeInsetsDirectional.only(top: AppSpacing.lg)});

  final String placement;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ad = ref.watch(placementAdProvider(placement)).value;
    if (ad == null) return const SizedBox.shrink();
    return Padding(padding: padding, child: _AdCard(ad: ad));
  }
}

class _AdCard extends ConsumerStatefulWidget {
  const _AdCard({required this.ad});

  final AdCreative ad;

  @override
  ConsumerState<_AdCard> createState() => _AdCardState();
}

class _AdCardState extends ConsumerState<_AdCard> {
  Timer? _impression;
  bool _reported = false;

  @override
  void initState() {
    super.initState();
    // Counted after it has been on screen for a second, once.
    _impression = Timer(const Duration(seconds: 1), () => _report('impression'));
  }

  @override
  void dispose() {
    _impression?.cancel();
    super.dispose();
  }

  void _report(String type) {
    final token = widget.ad.token;
    if (token == null) return;
    if (type == 'impression') {
      if (_reported) return;
      _reported = true;
    }
    ref.read(adEventQueueProvider).add(token, type);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final ad = widget.ad;
    final native = ad.format == 'native';
    return Semantics(
      label: '${l.adLabel}: ${ad.title}',
      child: AppCard(
        padding: EdgeInsets.zero,
        onTap: ad.actionUrl == null
            ? null
            : () {
                _report('impression');
                _report('click');
                openAdAction(context, ad.actionUrl);
              },
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          if (native && ad.imageUrl != null)
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(AppRadius.md)),
              child: AspectRatio(aspectRatio: 2, child: NetImage(ad.imageUrl)),
            ),
          Padding(
            padding: const EdgeInsetsDirectional.all(AppSpacing.md),
            child: Row(children: [
              if (!native && ad.imageUrl != null) ...[
                ClipRRect(borderRadius: AppRadius.smAll, child: NetImage(ad.imageUrl, width: 48, height: 48)),
                const SizedBox(width: AppSpacing.md),
              ],
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    Container(
                      padding: const EdgeInsetsDirectional.symmetric(horizontal: 6, vertical: 1),
                      decoration: BoxDecoration(border: Border.all(color: p.border), borderRadius: BorderRadius.circular(4)),
                      child: Text(l.adLabel, style: context.text.labelSmall),
                    ),
                    if (ad.advertiser != null) ...[const SizedBox(width: AppSpacing.xs), Flexible(child: Text(ad.advertiser!, style: context.text.labelSmall, overflow: TextOverflow.ellipsis))],
                  ]),
                  const SizedBox(height: AppSpacing.xxs),
                  Text(ad.title, style: context.text.titleSmall, maxLines: 1, overflow: TextOverflow.ellipsis),
                  if (ad.body != null) Text(ad.body!, style: context.text.bodySmall, maxLines: native ? 3 : 1, overflow: TextOverflow.ellipsis),
                ]),
              ),
              if (ad.ctaLabel != null && ad.actionUrl != null) ...[
                const SizedBox(width: AppSpacing.sm),
                Text(ad.ctaLabel!, style: context.text.labelLarge?.copyWith(color: p.green)),
              ],
            ]),
          ),
        ]),
      ),
    );
  }
}
