import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/analytics/analytics.dart';
import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/permissions/permission_primer.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/trail.dart';
import '../../weather/presentation/weather_card.dart';
import '../../../core/sensors/step_platform.dart';
import '../../config/data/app_config.dart';
import '../application/active_walk_controller.dart';

/// Start / follow / finish a user-initiated walk.
class WalkPage extends ConsumerStatefulWidget {
  const WalkPage({super.key});

  @override
  ConsumerState<WalkPage> createState() => _WalkPageState();
}

class _WalkPageState extends ConsumerState<WalkPage> {
  bool _gps = false;

  Future<void> _toggleGps(bool value) async {
    if (value && !await PermissionPrimer.ensure(context, AppPermission.location)) return;
    setState(() => _gps = value);
  }

  Future<void> _start() async {
    if (!await PermissionPrimer.ensure(context, AppPermission.activity)) return;
    if (!mounted) return;
    // Optional: without it the walk still records, the ongoing notification is just hidden.
    await PermissionPrimer.ensure(context, AppPermission.notifications);
    await ref.read(activeWalkProvider.notifier).start(gps: _gps);
    ref.read(analyticsProvider).track('walking_started', {'gps': _gps});
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final state = ref.watch(activeWalkProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l.walkTitle)),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
          child: AnimatedSwitcher(
            duration: AppMotion.base,
            child: switch (state) {
              WalkIdle() => _Idle(key: const ValueKey('idle'), gps: _gps, onGps: _toggleGps, onStart: _start),
              WalkRunning(:final live) => _Live(key: const ValueKey('run'), live: live, onStop: () {
                ref.read(analyticsProvider).track('walking_completed', {'steps': live.steps, 'minutes': live.elapsed.inMinutes, 'gps': live.gps});
                ref.read(activeWalkProvider.notifier).stop();
              }),
              WalkSaving(:final live) => _Live(key: const ValueKey('save'), live: live, saving: true),
              WalkFinished() => _Finished(key: const ValueKey('done'), state: state, onDone: () {
                  ref.read(activeWalkProvider.notifier).reset();
                  Navigator.of(context).maybePop();
                }),
              WalkFailed(:final reason) => _Failed(key: const ValueKey('fail'), reason: reason, onRetry: () => ref.read(activeWalkProvider.notifier).reset()),
            },
          ),
        ),
      ),
    );
  }
}

class _Idle extends StatelessWidget {
  const _Idle({super.key, required this.gps, required this.onGps, required this.onStart});

  final bool gps;
  final ValueChanged<bool> onGps;
  final VoidCallback onStart;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(
          child: ListView(children: [
            const WeatherCard(hourCount: 4),
            const SizedBox(height: AppSpacing.xl),
            Text(l.walkIntro, style: context.text.bodyLarge?.copyWith(color: context.palette.inkMuted)),
            const SizedBox(height: AppSpacing.xl),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(l.walkGpsToggle),
              subtitle: Text(l.walkGpsHint),
              value: gps,
              onChanged: onGps,
            ),
          ]),
        ),
        const SizedBox(height: AppSpacing.md),
        AppButton(label: l.walkStart, icon: Icons.play_arrow_rounded, onPressed: onStart),
      ],
    );
  }
}

class _Live extends ConsumerWidget {
  const _Live({super.key, required this.live, this.onStop, this.saving = false});

  final LiveWalk live;
  final VoidCallback? onStop;
  final bool saving;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    final cycling = live.mode == MoveMode.cycling;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const WeatherStrip(),
        const Spacer(),
        Center(child: MoveModeBadge(mode: live.mode)),
        const SizedBox(height: AppSpacing.lg),
        Semantics(
          liveRegion: true,
          label: cycling ? '${Fa.decimal(live.cyclingDistanceM / 1000, decimals: 2)} ${l.homeUnitKm}' : '${Fa.number(live.steps)} ${l.activitySteps}',
          excludeSemantics: true,
          child: Column(children: [
            if (cycling)
              Text(Fa.decimal(live.cyclingDistanceM / 1000, decimals: 2), style: context.text.displayLarge?.copyWith(fontSize: 64))
            else
              TweenAnimationBuilder<double>(
                tween: Tween(end: live.steps.toDouble()),
                duration: AppMotion.base,
                builder: (_, v, _) => Text(Fa.number(v.round()), style: context.text.displayLarge?.copyWith(fontSize: 64)),
              ),
            Text(cycling ? l.walkCyclingDistance : l.activitySteps, style: context.text.bodyMedium?.copyWith(color: p.inkMuted)),
          ]),
        ),
        const SizedBox(height: AppSpacing.x3),
        Row(children: [
          Expanded(child: _Metric(label: l.walkElapsed, value: FaDate.clock(live.elapsed))),
          if (live.gps) Expanded(child: _Metric(label: l.walkDistance, value: '${Fa.decimal(live.distanceM / 1000, decimals: 2)} ${l.homeUnitKm}')),
          if (live.gps && live.speedKmh > 0) Expanded(child: _Metric(label: l.walkSpeed, value: '${Fa.number(live.speedKmh.round())} km/h')),
          if (cycling) Expanded(child: _Metric(label: l.activitySteps, value: Fa.number(live.steps))),
        ]),
        if (cycling || live.mode == MoveMode.vehicle) ...[
          const SizedBox(height: AppSpacing.lg),
          Text(
            live.mode == MoveMode.vehicle
                ? l.walkVehicleHint
                : (live.gps ? l.walkCyclingHint(Fa.number(ref.watch(configProvider).cyclingPointsPerKm)) : l.walkCyclingNeedsGps),
            style: context.text.bodySmall?.copyWith(color: live.mode == MoveMode.vehicle ? p.danger : p.inkMuted),
            textAlign: TextAlign.center,
          ),
        ],
        const Spacer(),
        AppButton(
          label: saving ? l.walkSaving : l.walkStop,
          icon: Icons.stop_rounded,
          variant: AppButtonVariant.danger,
          loading: saving,
          onPressed: onStop,
        ),
      ],
    );
  }
}

/// Big round picture of how the user is moving: a bicycle when riding, a walker on foot.
class MoveModeBadge extends StatelessWidget {
  const MoveModeBadge({super.key, required this.mode});

  final MoveMode mode;

  static IconData iconOf(MoveMode mode) => switch (mode) {
        MoveMode.cycling => Icons.pedal_bike_rounded,
        MoveMode.running => Icons.directions_run_rounded,
        MoveMode.vehicle => Icons.directions_car_rounded,
        MoveMode.walking => Icons.directions_walk_rounded,
        MoveMode.still => Icons.accessibility_new_rounded,
      };

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final (color, bg) = switch (mode) {
      MoveMode.cycling => (p.info, p.info.withValues(alpha: 0.12)),
      MoveMode.vehicle => (p.danger, p.dangerSoft),
      MoveMode.still => (p.inkSubtle, p.surfaceSunken),
      _ => (p.green, p.greenSoft),
    };
    final label = switch (mode) {
      MoveMode.cycling => l.walkModeCycling,
      MoveMode.running => l.walkModeRunning,
      MoveMode.vehicle => l.walkModeVehicle,
      MoveMode.walking => l.walkModeWalking,
      MoveMode.still => l.walkModeStill,
    };
    return Column(children: [
      AnimatedContainer(
        duration: AppMotion.base,
        width: 92,
        height: 92,
        decoration: BoxDecoration(color: bg, shape: BoxShape.circle, border: Border.all(color: color.withValues(alpha: 0.35), width: 2)),
        child: AnimatedSwitcher(duration: AppMotion.base, child: Icon(iconOf(mode), key: ValueKey(mode), size: 52, color: color)),
      ),
      const SizedBox(height: AppSpacing.xs),
      Text(label, style: context.text.labelLarge?.copyWith(color: color)),
    ]);
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Column(children: [
        Text(value, style: context.text.headlineSmall?.copyWith(fontFeatures: const [FontFeature.tabularFigures()])),
        Text(label, style: context.text.labelMedium),
      ]);
}

class _Finished extends StatelessWidget {
  const _Finished({super.key, required this.state, required this.onDone});

  final WalkFinished state;
  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Spacer(),
        const Trail(progress: 1, dots: 16, height: 64),
        const SizedBox(height: AppSpacing.x3),
        Text(l.walkDoneTitle, style: context.text.headlineSmall, textAlign: TextAlign.center),
        const SizedBox(height: AppSpacing.sm),
        Text(
          l.walkDoneBody(Fa.number(state.steps), Fa.digits(state.duration.inMinutes)),
          style: context.text.bodyMedium?.copyWith(color: p.inkMuted),
          textAlign: TextAlign.center,
        ),
        if (!state.synced) ...[
          const SizedBox(height: AppSpacing.md),
          Text(l.walkDoneOffline, style: context.text.bodySmall, textAlign: TextAlign.center),
        ],
        const Spacer(),
        AppButton(label: l.walkDone, onPressed: onDone),
      ],
    );
  }
}

class _Failed extends StatelessWidget {
  const _Failed({super.key, required this.reason, required this.onRetry});

  final String reason;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final message = switch (reason) {
      'no_sensor' => l.walkNoSensor,
      'permission' => l.walkNeedsPermission,
      _ => l.walkFailed,
    };
    return Center(
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(Icons.error_outline_rounded, color: context.palette.inkSubtle, size: 32),
        const SizedBox(height: AppSpacing.md),
        Text(message, style: context.text.bodyMedium, textAlign: TextAlign.center),
        const SizedBox(height: AppSpacing.lg),
        AppButton.secondary(label: l.commonRetry, onPressed: onRetry, expand: false),
      ]),
    );
  }
}
