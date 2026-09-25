import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/state_views.dart';
import '../../activity/application/activity_providers.dart';
import '../../wallet/data/wallet_repository.dart';
import '../data/gamification_models.dart';
import '../data/gamification_repository.dart';

Future<void> showStreakSheet(BuildContext context, StreakWeek streak) => showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (_) => StreakSheet(streak: streak),
    );

/// Current streak, freezes owned, and buying one more.
class StreakSheet extends ConsumerStatefulWidget {
  const StreakSheet({super.key, required this.streak});

  final StreakWeek streak;

  @override
  ConsumerState<StreakSheet> createState() => _StreakSheetState();
}

class _StreakSheetState extends ConsumerState<StreakSheet> {
  late StreakWeek _streak = widget.streak;
  String _key = const Uuid().v4();
  bool _busy = false;

  Future<void> _buy() async {
    setState(() => _busy = true);
    try {
      final updated = await buyStreakFreeze(ref.read(apiClientProvider), _key);
      _key = const Uuid().v4(); // next purchase is a new request
      ref.invalidate(homeProvider);
      ref.invalidate(progressProvider);
      ref.invalidate(walletBalanceProvider);
      if (mounted) {
        setState(() => _streak = updated);
        showAppSnack(context, context.l10n.freezeBought);
      }
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
    final f = _streak.freezes;
    return SafeArea(
      child: Padding(
        padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, 0, AppSpacing.gutter, AppSpacing.lg),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(children: [
            Icon(Icons.local_fire_department_rounded, color: p.goldInk, size: 32),
            const SizedBox(width: AppSpacing.sm),
            Expanded(child: Text(l.homeStreak7(Fa.digits(_streak.current)), style: context.text.headlineSmall)),
          ]),
          if (_streak.longest > 0) Text(l.streakLongest(Fa.digits(_streak.longest)), style: context.text.bodySmall),
          const SizedBox(height: AppSpacing.xl),
          Row(children: [
            Text(l.freezeTitle, style: context.text.titleMedium),
            const Spacer(),
            for (var i = 0; i < f.max; i++)
              Padding(
                padding: const EdgeInsetsDirectional.only(start: AppSpacing.xs),
                child: Icon(Icons.ac_unit_rounded, color: i < f.owned ? p.info : p.border),
              ),
          ]),
          const SizedBox(height: AppSpacing.xs),
          Text(l.freezeBody, style: context.text.bodyMedium?.copyWith(color: p.inkMuted)),
          const SizedBox(height: AppSpacing.lg),
          if (f.canBuy)
            AppButton(
              label: l.freezeBuy(Fa.number(f.price)),
              icon: Icons.ac_unit_rounded,
              variant: AppButtonVariant.reward,
              loading: _busy,
              onPressed: _buy,
            )
          else if (f.max > 0)
            Text(l.freezeFull, textAlign: TextAlign.center, style: context.text.bodySmall),
        ]),
      ),
    );
  }
}
