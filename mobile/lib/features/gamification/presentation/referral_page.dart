import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:share_plus/share_plus.dart';

import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/stat_tile.dart';
import '../../../core/widgets/state_views.dart';
import '../data/gamification_repository.dart';

class ReferralPage extends ConsumerWidget {
  const ReferralPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    return Scaffold(
      appBar: AppBar(title: Text(l.referralTitle)),
      body: AsyncView(
        value: ref.watch(referralProvider),
        onRetry: () => ref.invalidate(referralProvider),
        data: (r) => ListView(
          padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
          children: [
            Text(
              l.referralBody(Fa.number(r.qualifySteps), Fa.number(r.referrerPoints), Fa.number(r.refereePoints)),
              style: context.text.bodyMedium,
            ),
            const SizedBox(height: AppSpacing.xl),
            AppCard(
              color: p.greenSoft,
              borderColor: p.greenSoft,
              onTap: () async {
                await Clipboard.setData(ClipboardData(text: r.code));
                if (context.mounted) showAppSnack(context, l.referralCopied);
              },
              child: Row(children: [
                Expanded(
                  child: Directionality(
                    textDirection: TextDirection.ltr,
                    child: Text(r.code, style: context.text.headlineSmall?.copyWith(letterSpacing: 4, color: p.greenStrong), textAlign: TextAlign.center),
                  ),
                ),
                Icon(Icons.copy_rounded, color: p.greenStrong),
              ]),
            ),
            const SizedBox(height: AppSpacing.md),
            AppButton(
              label: l.referralShare,
              icon: Icons.share_rounded,
              onPressed: () => SharePlus.instance.share(ShareParams(text: l.referralShareText(r.code))),
            ),
            const SizedBox(height: AppSpacing.xl),
            AppCard(
              child: Row(children: [
                Expanded(child: StatTile(value: Fa.number(r.invited), unit: '', label: l.referralInvited)),
                Expanded(child: StatTile(value: Fa.number(r.rewarded), unit: '', label: l.referralActive)),
                Expanded(child: StatTile(value: Fa.number(r.pointsEarned), unit: '', label: l.referralPoints)),
              ]),
            ),
          ],
        ),
      ),
    );
  }
}
