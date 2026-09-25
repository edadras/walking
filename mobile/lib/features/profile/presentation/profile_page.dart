import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/state_views.dart';
import '../../auth/application/session_controller.dart';
import '../../gamification/data/gamification_repository.dart';
import '../../gamification/presentation/achievements_page.dart';
import '../data/profile_repository.dart';
import 'goal_sheet.dart';
import '../../../core/theme/theme_mode.dart';
import '../../../core/widgets/net_image.dart';

class ProfilePage extends ConsumerWidget {
  const ProfilePage({super.key});

  Future<void> _toggleLeaderboard(BuildContext context, WidgetRef ref, bool value) async {
    try {
      final me = await ref.read(profileRepositoryProvider).updateSettings({'leaderboard_visible': value});
      ref.read(sessionProvider.notifier).updateMe(me);
    } on ApiException catch (e) {
      if (context.mounted) showAppSnack(context, e.message);
    }
  }

  Future<void> _pickTheme(BuildContext context, WidgetRef ref) async {
    final l = context.l10n;
    final current = ref.read(themeModeProvider);
    final picked = await showModalBottomSheet<ThemeMode>(
      context: context,
      showDragHandle: true,
      builder: (c) => SafeArea(
        child: RadioGroup<ThemeMode>(
          groupValue: current,
          onChanged: (m) => Navigator.pop(c, m),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            for (final (mode, label) in [(ThemeMode.system, l.themeSystem), (ThemeMode.light, l.themeLight), (ThemeMode.dark, l.themeDark)])
              RadioListTile<ThemeMode>(value: mode, title: Text(label)),
          ]),
        ),
      ),
    );
    if (picked != null) await ref.read(themeModeProvider.notifier).set(picked);
  }

  Future<void> _logout(BuildContext context, WidgetRef ref) async {
    final l = context.l10n;
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        content: Text(l.profileLogoutConfirm),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.commonCancel)),
          TextButton(onPressed: () => Navigator.pop(c, true), child: Text(l.profileLogout)),
        ],
      ),
    );
    if (ok == true) await ref.read(sessionProvider.notifier).signOut();
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final me = ref.watch(meProvider);
    final l = context.l10n;
    final p = context.palette;

    Widget tile(IconData icon, String title, {String? trailing, VoidCallback? onTap, Color? color}) => ListTile(
          leading: Icon(icon, color: color),
          title: Text(title, style: color != null ? context.text.bodyLarge?.copyWith(color: color) : null),
          trailing: Row(mainAxisSize: MainAxisSize.min, children: [
            if (trailing != null) Text(trailing, style: context.text.bodySmall),
            Icon(Icons.chevron_left_rounded, color: p.inkSubtle, textDirection: TextDirection.ltr),
          ]),
          onTap: onTap,
        );

    Widget section(String title) => Padding(
          padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, AppSpacing.xxl, AppSpacing.gutter, AppSpacing.xs),
          child: Text(title, style: context.text.labelMedium),
        );

    return Scaffold(
      appBar: AppBar(title: Text(l.profileTitle)),
      body: ListView(
        padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.x4),
        children: [
          Padding(
            padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter),
            child: AppCard(
              onTap: () => context.push('/profile/edit'),
              child: Row(children: [
                _Avatar(url: me.avatarUrl, name: me.publicName),
                const SizedBox(width: AppSpacing.lg),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(me.publicName, style: context.text.titleMedium),
                    const SizedBox(height: AppSpacing.xxs),
                    Text(
                      [l.profileLevel(Fa.digits(me.level)), if (me.joinedAt != null) l.profileJoined(FaDate.monthYear(me.joinedAt!))].join(' · '),
                      style: context.text.bodySmall,
                    ),
                  ]),
                ),
                Icon(Icons.edit_outlined, size: 20, color: p.inkSubtle),
              ]),
            ),
          ),
          Padding(
            padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, AppSpacing.md, AppSpacing.gutter, 0),
            child: switch (ref.watch(progressProvider).value) {
              final progress? => LevelBar(level: progress.$1, onTap: () => context.push('/achievements')),
              null => const SizedBox.shrink(),
            },
          ),
          section(l.profileSectionActivity),
          tile(Icons.emoji_events_outlined, l.profileAchievements, onTap: () => context.push('/achievements')),
          tile(Icons.leaderboard_outlined, l.profileLeaderboard, onTap: () => context.push('/leaderboard')),
          tile(Icons.people_outline_rounded, l.profileFriends, onTap: () => context.push('/friends')),
          tile(Icons.apartment_rounded, l.profileOrganization, onTap: () => context.push('/organization')),
          tile(Icons.insights_rounded, l.profileHealth, onTap: () => context.push('/health')),
          tile(Icons.local_drink_outlined, l.profileWater, onTap: () => context.push('/water')),
          tile(Icons.group_add_outlined, l.profileReferral, onTap: () => context.push('/referral')),
          tile(Icons.confirmation_number_outlined, l.profileCoupons, onTap: () => context.push('/coupons')),
          tile(Icons.receipt_long_outlined, l.profileOrders, onTap: () => context.push('/orders')),
          tile(Icons.home_work_outlined, l.profileAddresses, onTap: () => context.push('/addresses')),
          tile(Icons.flag_outlined, l.profileDailyGoal,
              trailing: l.goalSteps(Fa.number(me.dailyStepGoal)), onTap: () => showDailyGoalSheet(context, ref)),
          tile(Icons.water_drop_outlined, l.profileWaterGoal,
              trailing: '${Fa.number(me.waterGoalMl)} ml', onTap: () => showWaterGoalSheet(context, ref)),
          section(l.profileSectionPrivacy),
          SwitchListTile(
            secondary: const Icon(Icons.leaderboard_outlined),
            title: Text(l.profileLeaderboardVisible),
            subtitle: Text(l.profileLeaderboardVisibleHint),
            value: me.leaderboardVisible,
            onChanged: (v) => _toggleLeaderboard(context, ref, v),
          ),
          tile(Icons.dark_mode_outlined, l.profileTheme,
              trailing: switch (ref.watch(themeModeProvider)) {
                ThemeMode.light => l.themeLight,
                ThemeMode.dark => l.themeDark,
                ThemeMode.system => l.themeSystem,
              },
              onTap: () => _pickTheme(context, ref)),
          tile(Icons.notifications_none_rounded, l.profileNotifications, onTap: () => context.push('/profile/notifications')),
          tile(Icons.devices_outlined, l.profileDevices, onTap: () => context.push('/profile/devices')),
          tile(Icons.person_remove_outlined, l.profileDeleteAccount, onTap: () => context.push('/profile/delete')),
          section(l.profileSectionAbout),
          tile(Icons.toll_outlined, l.profileHowToEarn, onTap: () => context.push('/page/how-to-earn')),
          tile(Icons.rule_rounded, l.profileRewardRules, onTap: () => context.push('/page/reward-rules')),
          tile(Icons.help_outline_rounded, l.profileFaq, onTap: () => context.push('/faq')),
          tile(Icons.support_agent_rounded, l.profileSupport, onTap: () => context.push('/support')),
          tile(Icons.gavel_rounded, l.profileTerms, onTap: () => context.push('/page/terms')),
          tile(Icons.privacy_tip_outlined, l.profilePrivacy, onTap: () => context.push('/page/privacy')),
          tile(Icons.info_outline_rounded, l.profileAbout, onTap: () => context.push('/page/about')),
          const SizedBox(height: AppSpacing.lg),
          tile(Icons.logout_rounded, l.profileLogout, color: p.danger, onTap: () => _logout(context, ref)),
          const SizedBox(height: AppSpacing.lg),
          Center(child: Text(l.profileVersion(Fa.digits(ref.watch(appVersionProvider))), style: context.text.labelSmall)),
        ],
      ),
    );
  }
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.url, required this.name});

  final String? url;
  final String name;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return NetAvatar(
      radius: 28,
      backgroundColor: p.greenSoft,
      url: url,
      child: Text(name.isEmpty ? '؟' : name.characters.first, style: context.text.titleMedium?.copyWith(color: p.greenStrong)),
    );
  }
}
