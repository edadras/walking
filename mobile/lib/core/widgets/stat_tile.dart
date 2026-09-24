import 'package:flutter/material.dart';

import '../theme/app_palette.dart';
import '../theme/tokens.dart';

/// Compact metric: value + unit on one line, label under it.
class StatTile extends StatelessWidget {
  const StatTile({super.key, required this.value, required this.unit, required this.label, this.icon});

  final String value;
  final String unit;
  final String label;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final t = context.text;
    return Semantics(
      label: '$label: $value $unit',
      excludeSemantics: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[Icon(icon, size: 18, color: p.inkSubtle), const SizedBox(height: AppSpacing.sm)],
          Text.rich(
            TextSpan(children: [
              TextSpan(text: value, style: t.titleMedium?.copyWith(fontWeight: FontWeight.w700, fontFeatures: const [FontFeature.tabularFigures()])),
              TextSpan(text: ' $unit', style: t.bodySmall),
            ]),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          Text(label, style: t.labelSmall, maxLines: 1, overflow: TextOverflow.ellipsis),
        ],
      ),
    );
  }
}

/// Yellow dot + number: the only place gold is used as a fill in body UI.
class PointsChip extends StatelessWidget {
  const PointsChip({super.key, required this.label, this.large = false});

  final String label;
  final bool large;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Container(
      padding: EdgeInsetsDirectional.symmetric(horizontal: large ? AppSpacing.md : AppSpacing.sm, vertical: large ? AppSpacing.xs : AppSpacing.xxs),
      decoration: BoxDecoration(color: p.goldSoft, borderRadius: const BorderRadius.all(Radius.circular(AppRadius.xs))),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(width: large ? 8 : 6, height: large ? 8 : 6, decoration: BoxDecoration(color: p.gold, shape: BoxShape.circle)),
          SizedBox(width: large ? AppSpacing.sm : AppSpacing.xs),
          Text(label, style: (large ? context.text.titleSmall : context.text.labelMedium)?.copyWith(color: p.goldInk, fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}
