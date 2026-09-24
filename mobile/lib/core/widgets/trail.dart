import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../localization/l10n.dart';
import '../theme/app_palette.dart';
import '../theme/tokens.dart';

/// The brand motif: a dotted trail where every dot is a step and progress
/// means dots filling in along the path.
class TrailPainter extends CustomPainter {
  TrailPainter({
    required this.progress,
    required this.filled,
    required this.empty,
    this.accent,
    this.dots = 14,
    this.dotRadius = 3.2,
    this.amplitude = 0.28,
  });

  /// 0..1 — share of dots filled.
  final double progress;
  final Color filled;
  final Color empty;

  /// Colour of the final dot once the trail is complete (the "reward").
  final Color? accent;
  final int dots;
  final double dotRadius;

  /// Height of the S-curve relative to the box height.
  final double amplitude;

  Path _path(Size size) {
    final h = size.height;
    final w = size.width;
    final mid = h / 2;
    final a = h * amplitude;
    // Starts on the right (RTL reading direction) and meanders left.
    return Path()
      ..moveTo(w - dotRadius, mid + a)
      ..cubicTo(w * 0.62, mid + a * 1.6, w * 0.62, mid - a * 1.6, w * 0.4, mid - a * 0.4)
      ..cubicTo(w * 0.22, mid + a * 0.6, w * 0.14, mid - a, dotRadius, mid - a);
  }

  @override
  void paint(Canvas canvas, Size size) {
    final metric = _path(size).computeMetrics().first;
    final filledCount = (progress.clamp(0, 1) * dots).round();
    final paint = Paint()..isAntiAlias = true;

    for (var i = 0; i < dots; i++) {
      final t = dots == 1 ? 0.0 : i / (dots - 1);
      final pos = metric.getTangentForOffset(metric.length * t)!.position;
      final isLast = i == dots - 1;
      final isFilled = i < filledCount;
      paint.color = isLast && isFilled && accent != null ? accent! : (isFilled ? filled : empty);
      final r = isLast ? dotRadius * 1.5 : dotRadius;
      if (isLast && !isFilled) {
        canvas.drawCircle(pos, r, paint..style = PaintingStyle.stroke..strokeWidth = 1.5);
        paint.style = PaintingStyle.fill;
      } else {
        canvas.drawCircle(pos, r, paint..style = PaintingStyle.fill);
      }
    }
  }

  @override
  bool shouldRepaint(TrailPainter old) =>
      old.progress != progress || old.filled != filled || old.empty != empty || old.accent != accent || old.dots != dots;
}

/// Static trail, e.g. for empty states and onboarding.
class Trail extends StatelessWidget {
  const Trail({super.key, this.progress = 0.6, this.dots = 14, this.height = 56, this.width, this.showAccent = true});

  final double progress;
  final int dots;
  final double height;
  final double? width;
  final bool showAccent;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return ExcludeSemantics(
      child: SizedBox(
        height: height,
        width: width ?? double.infinity,
        child: CustomPaint(
          painter: TrailPainter(progress: progress, filled: p.green, empty: p.border, accent: showAccent ? p.gold : null, dots: dots),
        ),
      ),
    );
  }
}

/// Loading indicator in the brand language: three dots filling along a short
/// trail. Used instead of a bare spinner.
class TrailLoader extends StatefulWidget {
  const TrailLoader({super.key, this.size = 36});

  final double size;

  @override
  State<TrailLoader> createState() => _TrailLoaderState();
}

class _TrailLoaderState extends State<TrailLoader> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 900))..repeat();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final reduceMotion = MediaQuery.of(context).disableAnimations;
    return Semantics(
      label: context.l10n.commonLoading,
      child: SizedBox(
        width: widget.size * 1.6,
        height: widget.size * 0.5,
        child: AnimatedBuilder(
          animation: _c,
          builder: (context, _) {
            final active = reduceMotion ? 3 : (_c.value * 4).floor();
            return Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: List.generate(3, (i) {
                final on = i < active;
                return AnimatedContainer(
                  duration: AppMotion.fast,
                  width: widget.size * 0.22,
                  height: widget.size * 0.22,
                  decoration: BoxDecoration(shape: BoxShape.circle, color: on ? p.green : p.border),
                );
              }),
            );
          },
        ),
      ),
    );
  }
}

/// Circular variant of the trail used by [StepRing]; exposed for reuse in
/// achievements (dotted medal rims).
class DotRingPainter extends CustomPainter {
  DotRingPainter({
    required this.progress,
    required this.filled,
    required this.empty,
    required this.accent,
    this.dots = 60,
    this.dotRadius = 3.4,
  });

  final double progress;
  final Color filled;
  final Color empty;
  final Color accent;
  final int dots;
  final double dotRadius;

  @override
  void paint(Canvas canvas, Size size) {
    final center = size.center(Offset.zero);
    final radius = math.min(size.width, size.height) / 2 - dotRadius * 2;
    final clamped = progress.clamp(0.0, 1.0);
    final filledCount = (clamped * dots).floor();
    final complete = clamped >= 1;
    final paint = Paint()..isAntiAlias = true;

    for (var i = 0; i < dots; i++) {
      // Start at 12 o'clock and go counter-clockwise (the RTL reading direction).
      final angle = -math.pi / 2 - (2 * math.pi * i / dots);
      final pos = center + Offset(math.cos(angle), math.sin(angle)) * radius;
      final isFilled = i < filledCount;
      final isHead = !complete && i == filledCount && filledCount > 0;

      if (complete && i == dots - 1) {
        paint.color = accent;
        canvas.drawCircle(pos, dotRadius * 1.8, paint);
      } else if (isHead) {
        paint.color = filled;
        canvas.drawCircle(pos, dotRadius * 1.7, paint);
      } else {
        paint.color = isFilled ? filled : empty;
        canvas.drawCircle(pos, isFilled ? dotRadius : dotRadius * 0.8, paint);
      }
    }
  }

  @override
  bool shouldRepaint(DotRingPainter old) =>
      old.progress != progress || old.filled != filled || old.empty != empty || old.dots != dots;
}
