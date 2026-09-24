import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/state_views.dart';
import '../../rewards/data/rewards_repository.dart';
import '../application/ad_providers.dart';
import '../data/ads_repository.dart';
import 'ad_slot.dart';
import '../../../core/widgets/net_image.dart';

/// Full-screen internal rewarded ad. The countdown is only UX: the server
/// checks the elapsed time itself before paying.
class RewardedAdPage extends ConsumerStatefulWidget {
  const RewardedAdPage({super.key});

  @override
  ConsumerState<RewardedAdPage> createState() => _RewardedAdPageState();
}

class _RewardedAdPageState extends ConsumerState<RewardedAdPage> {
  RewardedView? _view;
  Object? _error;
  int _left = 0;
  Timer? _tick;
  bool _claiming = false;

  @override
  void initState() {
    super.initState();
    _start();
  }

  @override
  void dispose() {
    _tick?.cancel();
    super.dispose();
  }

  Future<void> _start() async {
    try {
      final v = await ref.read(adsRepositoryProvider).startRewarded(rewardedPlacement);
      if (!mounted) return;
      setState(() {
        _view = v;
        _left = v.ad.minViewSeconds;
      });
      _tick = Timer.periodic(const Duration(seconds: 1), (t) {
        if (!mounted) return;
        setState(() => _left = (_left - 1).clamp(0, 1 << 20));
        if (_left == 0) t.cancel();
      });
    } catch (e) {
      if (mounted) setState(() => _error = e);
    }
  }

  Future<void> _claim() async {
    setState(() => _claiming = true);
    try {
      final v = await ref.read(adsRepositoryProvider).completeRewarded(_view!.id);
      ref.invalidate(rewardedStatusProvider);
      ref.invalidate(rewardCenterProvider);
      if (mounted) setState(() => _view = v);
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _claiming = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final v = _view;
    return Scaffold(
      appBar: AppBar(title: Text(l.rewardedTitle)),
      body: switch ((v, _error)) {
        (null, null) => const LoadingView(),
        (null, final Object e) => ErrorView(error: e, onRetry: () => context.pop()),
        (final RewardedView v, _) => Padding(
            padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Expanded(
                child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  if (v.ad.imageUrl != null)
                    ClipRRect(borderRadius: AppRadius.mdAll, child: NetImage(v.ad.imageUrl, height: 220)),
                  const SizedBox(height: AppSpacing.xl),
                  if (v.ad.advertiser != null) Text(v.ad.advertiser!, style: context.text.labelMedium),
                  Text(v.ad.title, style: context.text.headlineSmall, textAlign: TextAlign.center),
                  if (v.ad.body != null) ...[const SizedBox(height: AppSpacing.sm), Text(v.ad.body!, style: context.text.bodyLarge, textAlign: TextAlign.center)],
                  if (v.ad.ctaLabel != null && v.ad.actionUrl != null) ...[
                    const SizedBox(height: AppSpacing.lg),
                    AppButton.ghost(label: v.ad.ctaLabel!, onPressed: () => openAdAction(context, v.ad.actionUrl)),
                  ],
                ]),
              ),
              if (v.status == 'started') ...[
                if (_left > 0) ...[
                  LinearProgressIndicator(value: 1 - _left / v.ad.minViewSeconds, color: p.gold, backgroundColor: p.surfaceSunken),
                  const SizedBox(height: AppSpacing.sm),
                  Text(l.rewardedWait(Fa.digits(_left)), textAlign: TextAlign.center, style: context.text.bodyMedium),
                  Text(l.rewardedLeaveHint, textAlign: TextAlign.center, style: context.text.bodySmall),
                ] else
                  AppButton(label: l.rewardedClaim(Fa.number(v.rewardPoints)), icon: Icons.toll_rounded, loading: _claiming, onPressed: _claim),
              ] else ...[
                Icon(v.status == 'rewarded' ? Icons.check_circle_rounded : Icons.info_outline_rounded, size: 48, color: v.status == 'rewarded' ? p.green : p.inkSubtle),
                const SizedBox(height: AppSpacing.sm),
                Text(v.status == 'rewarded' ? l.rewardedDone : l.rewardedFailed, textAlign: TextAlign.center, style: context.text.titleMedium),
                if (v.status == 'rewarded') Text(l.rewardedDoneBody(Fa.number(v.pointsAwarded)), textAlign: TextAlign.center, style: context.text.bodySmall),
                const SizedBox(height: AppSpacing.lg),
                AppButton.secondary(label: l.visitBack, onPressed: () => context.pop()),
              ],
              const SizedBox(height: AppSpacing.lg),
            ]),
          ),
      },
    );
  }
}

/// Entry card on the Reward Center (hidden when unavailable).
class RewardedAdCard extends ConsumerWidget {
  const RewardedAdCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final status = ref.watch(rewardedStatusProvider).value;
    if (status == null || !status.available) return const SizedBox.shrink();
    final l = context.l10n;
    final p = context.palette;
    return Padding(
      padding: const EdgeInsetsDirectional.only(top: AppSpacing.md),
      child: Material(
        color: p.goldSoft,
        borderRadius: AppRadius.mdAll,
        child: InkWell(
          borderRadius: AppRadius.mdAll,
          onTap: () => context.push('/rewarded-ad'),
          child: Padding(
            padding: const EdgeInsetsDirectional.all(AppSpacing.lg),
            child: Row(children: [
              Icon(Icons.play_circle_fill_rounded, color: p.goldInk, size: 32),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(l.rewardedCardTitle, style: context.text.titleSmall?.copyWith(color: p.goldInk)),
                  Text(l.rewardedCardBody(Fa.digits(status.remainingToday)), style: context.text.bodySmall),
                ]),
              ),
              Icon(Icons.chevron_left_rounded, color: p.goldInk, textDirection: TextDirection.ltr),
            ]),
          ),
        ),
      ),
    );
  }
}
