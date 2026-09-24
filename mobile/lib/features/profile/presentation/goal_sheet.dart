import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_text_field.dart';
import '../../../core/widgets/state_views.dart';
import '../../auth/application/session_controller.dart';
import '../../config/data/app_config.dart';
import '../data/profile_repository.dart';

Future<void> showDailyGoalSheet(BuildContext context, WidgetRef ref) => showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _DailyGoalSheet(),
    );

Future<void> showWaterGoalSheet(BuildContext context, WidgetRef ref) => showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _WaterGoalSheet(),
    );

class _DailyGoalSheet extends ConsumerStatefulWidget {
  const _DailyGoalSheet();

  @override
  ConsumerState<_DailyGoalSheet> createState() => _DailyGoalSheetState();
}

class _DailyGoalSheetState extends ConsumerState<_DailyGoalSheet> {
  late int _selected = ref.read(meProvider).dailyStepGoal;
  final _custom = TextEditingController();
  bool _customMode = false;
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _custom.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final config = ref.read(configProvider);
    final l = context.l10n;
    final value = _customMode ? int.tryParse(_custom.text) : _selected;
    if (value == null || value < config.minDailyGoal || value > config.maxDailyGoal) {
      setState(() => _error = l.goalRange(Fa.number(config.minDailyGoal), Fa.number(config.maxDailyGoal)));
      return;
    }
    setState(() => _saving = true);
    try {
      final me = await ref.read(profileRepositoryProvider).updateSettings({'daily_step_goal': value});
      ref.read(sessionProvider.notifier).updateMe(me);
      if (mounted) Navigator.pop(context);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final options = ref.watch(configProvider).dailyGoalOptions;

    return Padding(
      padding: EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, 0, AppSpacing.gutter, MediaQuery.of(context).viewInsets.bottom + AppSpacing.xxl),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.goalTitle, style: context.text.headlineSmall),
          const SizedBox(height: AppSpacing.xs),
          Text(l.goalSubtitle, style: context.text.bodySmall),
          const SizedBox(height: AppSpacing.xl),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              for (final o in options)
                _Choice(
                  label: Fa.number(o),
                  selected: !_customMode && _selected == o,
                  onTap: () => setState(() {
                    _customMode = false;
                    _selected = o;
                    _error = null;
                  }),
                ),
              _Choice(label: l.goalCustom, selected: _customMode, onTap: () => setState(() => _customMode = true)),
            ],
          ),
          if (_customMode) ...[
            const SizedBox(height: AppSpacing.lg),
            AppTextField(
              label: l.goalCustom,
              controller: _custom,
              autofocus: true,
              keyboardType: TextInputType.number,
              textDirection: TextDirection.ltr,
              inputFormatters: const [DigitsOnlyFormatter()],
              maxLength: 5,
            ),
          ],
          if (_error != null) ...[
            const SizedBox(height: AppSpacing.sm),
            Text(_error!, style: context.text.bodySmall?.copyWith(color: p.danger)),
          ],
          const SizedBox(height: AppSpacing.xxl),
          AppButton(label: l.commonSave, onPressed: _save, loading: _saving),
        ],
      ),
    );
  }
}

class _WaterGoalSheet extends ConsumerStatefulWidget {
  const _WaterGoalSheet();

  @override
  ConsumerState<_WaterGoalSheet> createState() => _WaterGoalSheetState();
}

class _WaterGoalSheetState extends ConsumerState<_WaterGoalSheet> {
  late int _ml = ref.read(meProvider).waterGoalMl;
  bool _saving = false;

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      final me = await ref.read(profileRepositoryProvider).updateSettings({'water_goal_ml': _ml});
      ref.read(sessionProvider.notifier).updateMe(me);
      if (mounted) Navigator.pop(context);
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final glass = ref.watch(configProvider).glassMl;
    return Padding(
      padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, 0, AppSpacing.gutter, AppSpacing.xxl),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.waterGoalTitle, style: context.text.headlineSmall),
          const SizedBox(height: AppSpacing.xs),
          Text(l.waterGoalNote, style: context.text.bodySmall),
          const SizedBox(height: AppSpacing.xl),
          Center(child: Text('${Fa.number(_ml)} ml', style: context.text.displaySmall)),
          Center(child: Text(l.waterGlasses(Fa.decimal(_ml / glass)), style: context.text.bodySmall)),
          Slider(
            value: _ml.toDouble(),
            min: 500,
            max: 6000,
            divisions: (6000 - 500) ~/ 250,
            label: Fa.number(_ml),
            onChanged: (v) => setState(() => _ml = v.round()),
          ),
          const SizedBox(height: AppSpacing.lg),
          AppButton(label: l.commonSave, onPressed: _save, loading: _saving),
        ],
      ),
    );
  }
}

class _Choice extends StatelessWidget {
  const _Choice({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Semantics(
      selected: selected,
      button: true,
      child: Material(
        color: selected ? p.greenSoft : p.bg,
        shape: RoundedRectangleBorder(borderRadius: AppRadius.smAll, side: BorderSide(color: selected ? p.green : p.border)),
        child: InkWell(
          borderRadius: AppRadius.smAll,
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.lg, vertical: AppSpacing.md),
            child: Text(label, style: context.text.labelLarge?.copyWith(color: selected ? p.greenStrong : p.ink)),
          ),
        ),
      ),
    );
  }
}
