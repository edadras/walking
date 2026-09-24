import 'package:flutter/material.dart';

import '../localization/l10n.dart';
import '../theme/app_palette.dart';
import '../theme/tokens.dart';

/// Soft single-tone shimmer shared by all skeleton blocks on screen.
class Shimmer extends StatefulWidget {
  const Shimmer({super.key, required this.child});

  final Widget child;

  @override
  State<Shimmer> createState() => _ShimmerState();
}

class _ShimmerState extends State<Shimmer> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1400))..repeat();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.of(context).disableAnimations) return widget.child;
    final p = context.palette;
    return Semantics(
      label: context.l10n.commonLoading,
      child: AnimatedBuilder(
        animation: _c,
        builder: (context, child) => ShaderMask(
          blendMode: BlendMode.srcATop,
          shaderCallback: (rect) => LinearGradient(
            begin: AlignmentDirectional.centerEnd.resolve(Directionality.of(context)),
            end: AlignmentDirectional.centerStart.resolve(Directionality.of(context)),
            colors: [p.surfaceSunken, p.surface, p.surfaceSunken],
            stops: [(_c.value - 0.3).clamp(0, 1), _c.value, (_c.value + 0.3).clamp(0, 1)],
          ).createShader(rect),
          child: child,
        ),
        child: widget.child,
      ),
    );
  }
}

class SkeletonBox extends StatelessWidget {
  const SkeletonBox({super.key, this.width, required this.height, this.radius = AppRadius.sm, this.circle = false});

  final double? width;
  final double height;
  final double radius;
  final bool circle;

  @override
  Widget build(BuildContext context) => Container(
        width: circle ? height : width,
        height: height,
        decoration: BoxDecoration(
          color: context.palette.surfaceSunken,
          shape: circle ? BoxShape.circle : BoxShape.rectangle,
          borderRadius: circle ? null : BorderRadius.circular(radius),
        ),
      );
}

/// Generic list skeleton for pages that don't define their own.
class SkeletonList extends StatelessWidget {
  const SkeletonList({super.key, this.rows = 6});

  final int rows;

  @override
  Widget build(BuildContext context) => Shimmer(
        child: ListView.separated(
          physics: const NeverScrollableScrollPhysics(),
          padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
          itemCount: rows,
          separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.lg),
          itemBuilder: (_, _) => const Row(children: [
            SkeletonBox(height: 40, circle: true),
            SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                SkeletonBox(width: 160, height: 12),
                SizedBox(height: AppSpacing.sm),
                SkeletonBox(width: 100, height: 10),
              ]),
            ),
          ]),
        ),
      );
}
