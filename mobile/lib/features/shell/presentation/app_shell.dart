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

/// Temporary tab body for sections delivered in later phases.
class ComingNextPage extends StatelessWidget {
  const ComingNextPage({super.key, required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: Center(child: _ComingNext(title: l.comingNextTitle, body: l.comingNextBody)),
    );
  }
}

class _ComingNext extends StatelessWidget {
  const _ComingNext({required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.all(32),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Text(title, style: Theme.of(context).textTheme.titleMedium, textAlign: TextAlign.center),
          const SizedBox(height: 8),
          Text(body, style: Theme.of(context).textTheme.bodySmall, textAlign: TextAlign.center),
        ]),
      );
}
