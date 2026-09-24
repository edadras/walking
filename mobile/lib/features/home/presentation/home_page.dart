import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/stat_tile.dart';
import '../../../core/widgets/step_ring.dart';
import '../../auth/application/session_controller.dart';

/// Phase 1 home: identity, goal and the step ring. Live activity, points and
/// streak are wired in Phase 2–3 through the aggregated GET /home endpoint.
class HomePage extends ConsumerWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final me = ref.watch(meProvider);
    final l = context.l10n;
    final p = context.palette;
    const steps = 0;

    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          color: p.green,
          onRefresh: () => ref.read(sessionProvider.notifier).refreshMe(),
          child: ListView(
            padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter, vertical: AppSpacing.lg),
            children: [
              Text(l.homeGreeting(me.publicName), style: context.text.headlineSmall),
              Text(l.homeToday('${FaDate.weekday(DateTime.now())} ${FaDate.dayMonth(DateTime.now())}'), style: context.text.bodySmall),
              const SizedBox(height: AppSpacing.xxl),
              Center(child: StepRing(steps: steps, goal: me.dailyStepGoal)),
              const SizedBox(height: AppSpacing.md),
              Center(
                child: Text(l.homeRemaining(Fa.number(me.dailyStepGoal - steps)), style: context.text.bodyMedium?.copyWith(color: p.inkMuted)),
              ),
              const SizedBox(height: AppSpacing.xxl),
              AppCard(
                child: Row(children: [
                  Expanded(child: StatTile(value: Fa.decimal(0), unit: l.homeUnitKm, label: l.homeDistance, icon: Icons.route_outlined)),
                  Expanded(child: StatTile(value: Fa.number(0), unit: l.homeUnitKcal, label: l.homeCalories, icon: Icons.local_fire_department_outlined)),
                  Expanded(child: StatTile(value: Fa.number(0), unit: l.homeUnitMin, label: l.homeActiveTime, icon: Icons.timer_outlined)),
                ]),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
