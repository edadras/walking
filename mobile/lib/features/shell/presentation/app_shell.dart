import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/localization/l10n.dart';
import '../../../core/widgets/app_bottom_nav.dart';
import '../../activity/application/tracking_bootstrap.dart';

class AppShell extends StatelessWidget {
  const AppShell({super.key, required this.shell});

  final StatefulNavigationShell shell;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      body: TrackingBootstrap(child: shell),
      bottomNavigationBar: AppBottomNav(
        currentIndex: shell.currentIndex,
        onTap: (i) => shell.goBranch(i, initialLocation: i == shell.currentIndex),
        items: [
          AppNavItem(label: l.navHome, icon: Icons.home_outlined, activeIcon: Icons.home_rounded),
          AppNavItem(label: l.navActivity, icon: Icons.directions_walk_outlined, activeIcon: Icons.directions_walk_rounded),
          AppNavItem(label: l.navRewards, icon: Icons.redeem_outlined, activeIcon: Icons.redeem_rounded),
          AppNavItem(label: l.navStore, icon: Icons.storefront_outlined, activeIcon: Icons.storefront_rounded),
          AppNavItem(label: l.navProfile, icon: Icons.person_outline_rounded, activeIcon: Icons.person_rounded),
        ],
      ),
    );
  }
}
