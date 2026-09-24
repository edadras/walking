import 'package:flutter/material.dart';

import '../theme/app_palette.dart';
import '../theme/tokens.dart';
import 'trail.dart';

enum AppButtonVariant { primary, secondary, ghost, reward, danger }

class AppButton extends StatelessWidget {
  const AppButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.variant = AppButtonVariant.primary,
    this.icon,
    this.loading = false,
    this.expand = true,
  });

  const AppButton.secondary({super.key, required this.label, required this.onPressed, this.icon, this.loading = false, this.expand = true})
      : variant = AppButtonVariant.secondary;

  const AppButton.ghost({super.key, required this.label, required this.onPressed, this.icon, this.loading = false, this.expand = false})
      : variant = AppButtonVariant.ghost;

  final String label;
  final VoidCallback? onPressed;
  final AppButtonVariant variant;
  final IconData? icon;
  final bool loading;
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final (bg, fg, border) = switch (variant) {
      AppButtonVariant.primary => (p.green, Colors.white, p.green),
      AppButtonVariant.secondary => (p.bg, p.ink, p.border),
      AppButtonVariant.ghost => (Colors.transparent, p.green, Colors.transparent),
      AppButtonVariant.reward => (p.gold, p.ink, p.gold),
      AppButtonVariant.danger => (p.bg, p.danger, p.border),
    };
    final disabled = onPressed == null && !loading;

    final child = AnimatedSwitcher(
      duration: AppMotion.fast,
      child: loading
          ? const SizedBox(key: ValueKey('l'), height: 20, child: Center(child: TrailLoader(size: 28)))
          : Row(
              key: const ValueKey('c'),
              mainAxisSize: MainAxisSize.min,
              children: [
                if (icon != null) ...[Icon(icon, size: 20, color: fg), const SizedBox(width: AppSpacing.sm)],
                Flexible(child: Text(label, overflow: TextOverflow.ellipsis, style: context.text.labelLarge?.copyWith(color: fg))),
              ],
            ),
    );

    final button = Semantics(
      button: true,
      enabled: !disabled,
      child: Material(
        color: disabled ? p.surfaceSunken : bg,
        shape: RoundedRectangleBorder(borderRadius: AppRadius.smAll, side: BorderSide(color: disabled ? p.surfaceSunken : border)),
        child: InkWell(
          borderRadius: AppRadius.smAll,
          onTap: loading ? null : onPressed,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: AppSizes.buttonHeight, minWidth: AppSizes.minTouchTarget),
            child: Padding(
              padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.xl),
              child: Center(widthFactor: 1, child: DefaultTextStyle.merge(style: TextStyle(color: disabled ? p.inkSubtle : fg), child: child)),
            ),
          ),
        ),
      ),
    );

    return expand ? SizedBox(width: double.infinity, child: button) : button;
  }
}
