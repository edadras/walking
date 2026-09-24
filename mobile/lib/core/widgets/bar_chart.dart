import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../theme/app_palette.dart';
import '../theme/tokens.dart';

/// Minimal bar chart: thin bars, optional dashed goal line, sparse labels.
/// In RTL the first value is drawn on the right, matching reading order.
class MiniBarChart extends StatelessWidget {
  const MiniBarChart({
    super.key,
    required this.values,
    this.labels = const [],
    this.goal,
    this.highlightIndex,
    this.height = 120,
    this.semanticLabel,
  });

  final List<num> values;

  /// Same length as [values]; null entries are not drawn.
  final List<String?> labels;
  final num? goal;
  final int? highlightIndex;
  final double height;
  final String? semanticLabel;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final rtl = Directionality.of(context) == TextDirection.rtl;
    final labelStyle = context.text.labelSmall!;
    return Semantics(
      label: semanticLabel,
      excludeSemantics: semanticLabel != null,
      child: SizedBox(
        height: height + (labels.isEmpty ? 0 : 20),
        child: TweenAnimationBuilder<double>(
          tween: Tween(begin: 0, end: 1),
          duration: MediaQuery.of(context).disableAnimations ? Duration.zero : AppMotion.slow,
          curve: AppMotion.enter,
          builder: (context, t, _) => CustomPaint(
            size: Size.infinite,
            painter: _BarPainter(
              values: values,
              labels: labels,
              goal: goal,
              highlight: highlightIndex,
              progress: t,
              rtl: rtl,
              chartHeight: height,
              bar: p.green,
              barMuted: p.greenSoft,
              barHighlight: p.greenStrong,
              goalColor: p.gold,
              labelStyle: labelStyle,
            ),
          ),
        ),
      ),
    );
  }
}

class _BarPainter extends CustomPainter {
  _BarPainter({
    required this.values,
    required this.labels,
    required this.goal,
    required this.highlight,
    required this.progress,
    required this.rtl,
    required this.chartHeight,
    required this.bar,
    required this.barMuted,
    required this.barHighlight,
    required this.goalColor,
    required this.labelStyle,
  });

  final List<num> values;
  final List<String?> labels;
  final num? goal;
  final int? highlight;
  final double progress;
  final bool rtl;
  final double chartHeight;
  final Color bar;
  final Color barMuted;
  final Color barHighlight;
  final Color goalColor;
  final TextStyle labelStyle;

  @override
  void paint(Canvas canvas, Size size) {
    if (values.isEmpty) return;
    final maxValue = math.max(values.fold<num>(0, (m, v) => math.max(m, v)), goal ?? 0).toDouble();
    final slot = size.width / values.length;
    final barWidth = math.min(slot * 0.56, 14.0);
    final paint = Paint();

    for (var i = 0; i < values.length; i++) {
      final x = rtl ? size.width - slot * (i + 0.5) : slot * (i + 0.5);
      final v = values[i].toDouble();
      final h = maxValue <= 0 ? 0.0 : (v / maxValue) * chartHeight * progress;
      final rect = RRect.fromRectAndRadius(
        Rect.fromLTWH(x - barWidth / 2, chartHeight - math.max(h, v > 0 ? 2 : 1.5), barWidth, math.max(h, v > 0 ? 2 : 1.5)),
        const Radius.circular(3),
      );
      paint.color = v <= 0 ? barMuted : (i == highlight ? barHighlight : bar);
      canvas.drawRRect(rect, paint);

      final label = i < labels.length ? labels[i] : null;
      if (label != null) {
        final tp = TextPainter(text: TextSpan(text: label, style: labelStyle), textDirection: TextDirection.rtl)..layout();
        tp.paint(canvas, Offset(x - tp.width / 2, chartHeight + 4));
      }
    }

    if (goal != null && goal! > 0 && maxValue > 0) {
      final y = chartHeight - (goal! / maxValue) * chartHeight;
      final dash = Paint()
        ..color = goalColor
        ..strokeWidth = 1.2;
      for (double x = 0; x < size.width; x += 7) {
        canvas.drawLine(Offset(x, y), Offset(math.min(x + 3.5, size.width), y), dash);
      }
    }
  }

  @override
  bool shouldRepaint(_BarPainter old) => old.values != values || old.progress != progress || old.goal != goal || old.highlight != highlight;
}
