import 'package:flutter/material.dart';

import '../theme/app_palette.dart';
import '../theme/tokens.dart';

/// Flat card: surface + 1px border, no shadow.
class AppCard extends StatelessWidget {
  const AppCard({super.key, required this.child, this.padding = const EdgeInsetsDirectional.all(AppSpacing.lg), this.onTap, this.color, this.borderColor});

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;
  final Color? color;
  final Color? borderColor;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Material(
      color: color ?? p.surface,
      shape: RoundedRectangleBorder(borderRadius: AppRadius.mdAll, side: BorderSide(color: borderColor ?? p.border)),
      clipBehavior: Clip.antiAlias,
      child: InkWell(onTap: onTap, child: Padding(padding: padding, child: child)),
    );
  }
}

class SectionHeader extends StatelessWidget {
  const SectionHeader({super.key, required this.title, this.actionLabel, this.onAction});

  final String title;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsetsDirectional.only(top: AppSpacing.xxl, bottom: AppSpacing.md),
      child: Row(
        children: [
          Expanded(child: Text(title, style: context.text.titleMedium)),
          if (actionLabel != null)
            TextButton(
              onPressed: onAction,
              style: TextButton.styleFrom(minimumSize: const Size(48, 40), foregroundColor: context.palette.green),
              child: Text(actionLabel!, style: context.text.labelLarge?.copyWith(color: context.palette.green)),
            ),
        ],
      ),
    );
  }
}
