import 'package:flutter/material.dart';

import '../theme/app_palette.dart';
import '../theme/tokens.dart';

class AppNavItem {
  const AppNavItem({required this.label, required this.icon, required this.activeIcon});

  final String label;
  final IconData icon;
  final IconData activeIcon;
}

/// Slim custom bottom navigation: line icons, small labels, and a single green
/// dot under the active item instead of a large pill.
class AppBottomNav extends StatelessWidget {
  const AppBottomNav({super.key, required this.items, required this.currentIndex, required this.onTap});

  final List<AppNavItem> items;
  final int currentIndex;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return DecoratedBox(
      decoration: BoxDecoration(color: p.bg, border: Border(top: BorderSide(color: p.border))),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 62,
          child: Row(
            children: [
              for (var i = 0; i < items.length; i++)
                Expanded(child: _NavButton(item: items[i], selected: i == currentIndex, onTap: () => onTap(i))),
            ],
          ),
        ),
      ),
    );
  }
}

class _NavButton extends StatelessWidget {
  const _NavButton({required this.item, required this.selected, required this.onTap});

  final AppNavItem item;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final color = selected ? p.green : p.inkSubtle;
    return Semantics(
      button: true,
      selected: selected,
      label: item.label,
      excludeSemantics: true,
      child: InkResponse(
        onTap: onTap,
        radius: 36,
        highlightColor: Colors.transparent,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            AnimatedSwitcher(
              duration: AppMotion.fast,
              child: Icon(selected ? item.activeIcon : item.icon, key: ValueKey(selected), color: color, size: AppSizes.iconMd),
            ),
            const SizedBox(height: 3),
            Text(
              item.label,
              maxLines: 1,
              style: context.text.labelSmall?.copyWith(color: color, fontWeight: selected ? FontWeight.w700 : FontWeight.w500),
            ),
            const SizedBox(height: 3),
            AnimatedContainer(
              duration: AppMotion.base,
              curve: AppMotion.enter,
              width: selected ? 4 : 0,
              height: 4,
              decoration: BoxDecoration(color: p.green, shape: BoxShape.circle),
            ),
          ],
        ),
      ),
    );
  }
}
