import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/permissions/permission_primer.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/state_views.dart';
import '../../activity/application/activity_providers.dart';
import '../../activity/application/tracking_service.dart';
import '../../auth/application/session_controller.dart';
import '../../profile/data/profile_repository.dart';
import '../application/water_reminders.dart';
import '../data/health_models.dart';
import '../data/health_repository.dart';

class WaterPage extends ConsumerStatefulWidget {
  const WaterPage({super.key});

  @override
  ConsumerState<WaterPage> createState() => _WaterPageState();
}

class _WaterPageState extends ConsumerState<WaterPage> {
  bool _busy = false;

  Future<void> _run(Future<void> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
      ref.invalidate(waterProvider);
      ref.invalidate(homeProvider);
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _setReminder(WaterDay day, bool enabled, int interval) async {
    if (enabled && !await PermissionPrimer.ensure(context, AppPermission.notifications)) return;
    await _run(() async {
      final me = await ref.read(profileRepositoryProvider).updateSettings({'water_reminder_enabled': enabled, 'water_reminder_interval_min': interval});
      ref.read(sessionProvider.notifier).updateMe(me);
      await ref.read(waterRemindersProvider).apply(enabled: enabled, intervalMinutes: interval, location: ref.read(dayClockProvider).location);
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final repo = ref.read(healthRepositoryProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.waterTitle)),
      body: AsyncView(
        value: ref.watch(waterProvider),
        onRetry: () => ref.invalidate(waterProvider),
        data: (day) => ListView(
          padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
          children: [
            _Glasses(day: day),
            const SizedBox(height: AppSpacing.xl),
            Row(children: [
              Expanded(child: AppButton(label: l.waterAddMl(Fa.number(day.glassMl)), icon: Icons.water_drop_outlined, loading: _busy, onPressed: () => _run(() => repo.addWater(day.glassMl)))),
              const SizedBox(width: AppSpacing.sm),
              Expanded(child: AppButton.secondary(label: l.waterAddMl(Fa.number(500)), onPressed: _busy ? null : () => _run(() => repo.addWater(500)))),
            ]),
            if (day.suggestedGoalMl != day.goalMl) ...[
              const SizedBox(height: AppSpacing.lg),
              AppCard(
                child: Row(children: [
                  Expanded(child: Text(l.waterSuggested(Fa.number(day.suggestedGoalMl)), style: context.text.bodySmall)),
                  TextButton(
                    onPressed: () => _run(() async {
                      final me = await ref.read(profileRepositoryProvider).updateSettings({'water_goal_ml': day.suggestedGoalMl});
                      ref.read(sessionProvider.notifier).updateMe(me);
                    }),
                    child: Text(l.waterUseSuggestion),
                  ),
                ]),
              ),
            ],
            const SizedBox(height: AppSpacing.md),
            AppCard(
              padding: EdgeInsets.zero,
              child: Column(children: [
                SwitchListTile(
                  title: Text(l.waterReminder),
                  subtitle: Text(l.waterReminderEvery(Fa.digits(day.reminderIntervalMin))),
                  value: day.reminderEnabled,
                  onChanged: _busy ? null : (v) => _setReminder(day, v, day.reminderIntervalMin),
                ),
                if (day.reminderEnabled)
                  Padding(
                    padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, 0, AppSpacing.gutter, AppSpacing.md),
                    child: Wrap(spacing: AppSpacing.sm, children: [
                      for (final m in [60, 90, 120, 180])
                        ChoiceChip(label: Text(Fa.digits(m)), selected: day.reminderIntervalMin == m, showCheckmark: false, onSelected: (_) => _setReminder(day, true, m)),
                    ]),
                  ),
              ]),
            ),
            SectionHeader(title: l.waterTodayLogs),
            if (day.logs.isEmpty)
              Text(l.waterEmpty, style: context.text.bodySmall)
            else
              for (final log in day.logs.reversed)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(Icons.water_drop_rounded, color: context.palette.info),
                  title: Text('${Fa.number(log.amountMl)} ml'),
                  subtitle: Text(FaDate.time(log.at)),
                  trailing: IconButton(icon: const Icon(Icons.close_rounded), tooltip: l.commonCancel, onPressed: () => _run(() => repo.deleteWater(log.id))),
                ),
            const SizedBox(height: AppSpacing.lg),
            Text(l.waterGoalNote, style: context.text.bodySmall?.copyWith(color: context.palette.inkSubtle)),
          ],
        ),
      ),
    );
  }
}

/// Glasses as dots on a row: filled = drunk. Mirrors the trail motif.
class _Glasses extends StatelessWidget {
  const _Glasses({required this.day});

  final WaterDay day;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final filled = day.glasses.floor();
    return Column(children: [
      Text(l.waterGlassesOf(Fa.decimal(day.glasses), Fa.digits(day.goalGlasses)), style: context.text.headlineSmall),
      Text('${Fa.number(day.totalMl)} / ${Fa.number(day.goalMl)} ml', style: context.text.bodySmall),
      const SizedBox(height: AppSpacing.lg),
      Wrap(
        alignment: WrapAlignment.center,
        spacing: AppSpacing.sm,
        runSpacing: AppSpacing.sm,
        children: [
          for (var i = 0; i < day.goalGlasses; i++)
            AnimatedContainer(
              duration: AppMotion.base,
              width: 28,
              height: 36,
              decoration: BoxDecoration(
                color: i < filled ? p.info.withValues(alpha: 0.85) : p.surface,
                border: Border.all(color: i < filled ? p.info : p.border),
                borderRadius: const BorderRadius.vertical(bottom: Radius.circular(8), top: Radius.circular(3)),
              ),
            ),
        ],
      ),
    ]);
  }
}
