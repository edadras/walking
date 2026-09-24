import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/permissions/permission_primer.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/stat_tile.dart';
import '../../../core/widgets/state_views.dart';
import '../application/location_source.dart';
import '../data/sponsor_models.dart';
import '../data/sponsor_repository.dart';
import 'sponsor_widgets.dart';

class CampaignPage extends ConsumerStatefulWidget {
  const CampaignPage({super.key, required this.id});

  final String id;

  @override
  ConsumerState<CampaignPage> createState() => _CampaignPageState();
}

class _CampaignPageState extends ConsumerState<CampaignPage> {
  String? _starting;

  Future<void> _start(CampaignDetail c, Branch branch) async {
    final l = context.l10n;
    if (!await PermissionPrimer.ensure(context, AppPermission.location)) return;
    setState(() => _starting = branch.id);
    try {
      final fix = await ref.read(locationSourceProvider).current();
      final visit = await ref.read(sponsorRepositoryProvider).startVisit(c.summary.id, branch.id, fix);
      if (mounted) context.push('/visits/${visit.id}');
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    } on LocationUnavailable catch (e) {
      if (mounted) showAppSnack(context, e.problem == LocationProblem.serviceOff ? l.nearbyLocationOff : l.nearbyLocationDenied);
    } finally {
      if (mounted) setState(() => _starting = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    return Scaffold(
      appBar: AppBar(),
      body: AsyncView(
        value: ref.watch(campaignProvider(widget.id)),
        onRetry: () => ref.invalidate(campaignProvider(widget.id)),
        data: (c) {
          final s = c.summary;
          final minutes = Fa.digits((s.minStaySeconds / 60).ceil());
          return ListView(
            padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, 0, AppSpacing.gutter, AppSpacing.xxl),
            children: [
              if (c.imageUrl != null) ...[
                ClipRRect(borderRadius: AppRadius.mdAll, child: AspectRatio(aspectRatio: 16 / 9, child: Image.network(c.imageUrl!, fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox()))),
                const SizedBox(height: AppSpacing.lg),
              ],
              Text(c.sponsor.name, style: context.text.labelMedium),
              Text(s.name, style: context.text.headlineSmall),
              if (c.description != null) ...[const SizedBox(height: AppSpacing.sm), Text(c.description!, style: context.text.bodyMedium)],
              const SizedBox(height: AppSpacing.lg),
              AppCard(
                child: Row(children: [
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      if (s.rewardPoints > 0) PointsChip(label: '+${Fa.number(s.rewardPoints)}', large: true),
                      if (s.coupon != null) ...[
                        const SizedBox(height: AppSpacing.sm),
                        Text(l.campaignCoupon(s.coupon!.title), style: context.text.bodyMedium?.copyWith(color: p.goldInk)),
                      ],
                    ]),
                  ),
                  Text(l.challengeEnds(FaDate.relativeFuture(s.endsAt)), style: context.text.labelSmall),
                ]),
              ),
              if (c.maxRewardsPerUser > 1 || c.myRewards > 0) ...[
                const SizedBox(height: AppSpacing.xs),
                Text(l.campaignMine(Fa.digits(c.myRewards), Fa.digits(c.maxRewardsPerUser)), style: context.text.bodySmall),
              ],
              SectionHeader(title: l.campaignHow),
              for (final (i, step) in [l.campaignStep1, l.campaignStep2(minutes), if (s.requiresQr) l.campaignStep3].indexed)
                Padding(
                  padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.sm),
                  child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    CircleAvatar(radius: 12, backgroundColor: p.greenSoft, child: Text(Fa.digits(i + 1), style: context.text.labelSmall?.copyWith(color: p.greenStrong))),
                    const SizedBox(width: AppSpacing.sm),
                    Expanded(child: Text(step, style: context.text.bodyMedium)),
                  ]),
                ),
              if (!s.eligible)
                AppCard(color: p.dangerSoft, borderColor: p.dangerSoft, child: Text(reasonLabel(context, s.ineligibleReason), style: context.text.bodyMedium?.copyWith(color: p.danger))),
              SectionHeader(title: l.campaignBranches),
              for (final b in c.branches)
                Padding(
                  padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.sm),
                  child: AppCard(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                      Text(b.name, style: context.text.titleSmall),
                      if (b.address != null) Text(b.address!, style: context.text.bodySmall),
                      if (!b.openNow) Text(l.branchClosed, style: context.text.bodySmall?.copyWith(color: p.danger)),
                      const SizedBox(height: AppSpacing.md),
                      AppButton(
                        label: l.campaignStart,
                        icon: Icons.place_rounded,
                        loading: _starting == b.id,
                        onPressed: s.eligible && b.openNow && _starting == null ? () => _start(c, b) : null,
                      ),
                    ]),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}
