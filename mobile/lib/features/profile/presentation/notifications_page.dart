import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/platform/home_widget.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/state_views.dart';
import '../data/profile_repository.dart';

const _labels = {
  'daily_goal': 'هدف روزانه',
  'water_reminder': 'یادآوری آب',
  'streak_warning': 'هشدار زنجیره روزها',
  'reward_received': 'دریافت امتیاز',
  'challenge': 'چالش‌ها',
  'sponsor_campaign': 'کمپین‌های اسپانسر',
  'coupon_expiration': 'انقضای کوپن',
  'order_update': 'وضعیت سفارش',
  'announcement': 'اطلاعیه‌ها',
};

class NotificationsPage extends ConsumerStatefulWidget {
  const NotificationsPage({super.key});

  @override
  ConsumerState<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends ConsumerState<NotificationsPage> {
  Map<String, bool>? _local;

  Future<void> _toggle(String key, bool value) async {
    final before = Map<String, bool>.from(_local!);
    setState(() => _local = {..._local!, key: value});
    try {
      final saved = await ref.read(profileRepositoryProvider).updateNotificationPreferences({key: value});
      setState(() => _local = saved);
    } on ApiException catch (e) {
      setState(() => _local = before);
      if (mounted) showAppSnack(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final async = ref.watch(notificationPrefsProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.notificationsTitle)),
      body: AsyncView(
        value: async,
        onRetry: () => ref.invalidate(notificationPrefsProvider),
        data: (prefs) {
          final values = _local ??= Map.of(prefs);
          return ListView(
            children: [
              for (final entry in _labels.entries)
                SwitchListTile(
                  title: Text(entry.value),
                  value: values[entry.key] ?? true,
                  onChanged: entry.key == 'order_update' ? null : (v) => _toggle(entry.key, v),
                ),
              const Divider(),
              // Device-only setting: today's steps on the lock screen (phones have no lock-screen widgets).
              Consumer(builder: (context, ref, _) {
                final lock = ref.watch(lockScreenStepsProvider).value;
                return SwitchListTile(
                  title: Text(l.lockScreenSteps),
                  subtitle: Text(l.lockScreenStepsHint),
                  value: lock ?? true,
                  onChanged: lock == null
                      ? null
                      : (v) async {
                          await ref.read(homeWidgetProvider).setLockScreen(v);
                          ref.invalidate(lockScreenStepsProvider);
                        },
                );
              }),
              Padding(
                padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
                child: Text(l.notificationsMandatory, style: context.text.bodySmall?.copyWith(color: context.palette.inkSubtle)),
              ),
            ],
          );
        },
      ),
    );
  }
}
