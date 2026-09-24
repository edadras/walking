import 'package:flutter/material.dart';

import '../format/numbers.dart';
import '../theme/app_palette.dart';
import '../theme/tokens.dart';
import 'trail.dart';

/// Home hero: a ring of 60 step-dots with the day's count in the middle.
class StepRing extends StatelessWidget {
  const StepRing({super.key, required this.steps, required this.goal, this.size = 244, this.caption});

  final int steps;
  final int goal;
  final double size;
  final String? caption;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final t = context.text;
    final progress = goal <= 0 ? 0.0 : steps / goal;
    final percent = (progress * 100).clamp(0, 999).round();
    final reached = progress >= 1;

    return Semantics(
      label: '${Fa.number(steps)} قدم از ${Fa.number(goal)}، ${Fa.percent(percent)}',
      excludeSemantics: true,
      child: SizedBox.square(
        dimension: size,
        child: TweenAnimationBuilder<double>(
          tween: Tween(begin: 0, end: progress),
          duration: MediaQuery.of(context).disableAnimations ? Duration.zero : const Duration(milliseconds: 900),
          curve: AppMotion.enter,
          builder: (context, value, _) {
            final shownSteps = goal <= 0 ? steps : (value * goal).round().clamp(0, steps);
            return Stack(
              alignment: Alignment.center,
              children: [
                CustomPaint(
                  size: Size.square(size),
                  painter: DotRingPainter(progress: value, filled: p.green, empty: p.border, accent: p.gold),
                ),
                Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(Fa.number(shownSteps), style: t.displayLarge),
                    const SizedBox(height: AppSpacing.xxs),
                    Text(caption ?? 'از ${Fa.number(goal)} قدم', style: t.bodySmall),
                    const SizedBox(height: AppSpacing.sm),
                    AnimatedContainer(
                      duration: AppMotion.base,
                      padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.sm, vertical: AppSpacing.xxs),
                      decoration: BoxDecoration(
                        color: reached ? p.goldSoft : p.greenSoft,
                        borderRadius: const BorderRadius.all(Radius.circular(AppRadius.xs)),
                      ),
                      child: Text(
                        reached ? 'هدف امروز کامل شد' : Fa.percent(percent),
                        style: t.labelMedium?.copyWith(color: reached ? p.goldInk : p.greenStrong, fontWeight: FontWeight.w700),
                      ),
                    ),
                  ],
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
