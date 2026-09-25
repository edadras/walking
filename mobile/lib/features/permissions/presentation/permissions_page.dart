import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/localization/l10n.dart';
import '../../../core/permissions/required_permissions.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/state_views.dart';
import '../../auth/application/session_controller.dart';

/// Blocking gate after sign-in: step tracking is the product, so without these
/// permissions every reward would silently be missed. The router sends the
/// user here whenever one is missing, including after revoking it later.
class PermissionsPage extends ConsumerWidget {
  const PermissionsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    final controller = ref.read(permissionGateProvider.notifier);

    return Scaffold(
      body: SafeArea(
        child: AsyncView(
          value: ref.watch(permissionGateProvider),
          onRetry: () => ref.invalidate(permissionGateProvider),
          data: (gate) {
            Widget step(RequiredPermission perm, IconData icon, String title, String body) => _Step(
                  icon: icon,
                  title: title,
                  body: body,
                  status: gate.statuses[perm]!,
                  onGrant: () => controller.request(perm),
                );

            return Column(children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
                  children: [
                    const SizedBox(height: AppSpacing.lg),
                    Container(
                      width: 64,
                      height: 64,
                      decoration: BoxDecoration(color: p.greenSoft, borderRadius: AppRadius.mdAll),
                      child: Icon(Icons.verified_user_outlined, color: p.green, size: 32),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    Text(l.gateTitle, style: context.text.headlineSmall),
                    const SizedBox(height: AppSpacing.sm),
                    Text(l.gateBody, style: context.text.bodyMedium?.copyWith(color: p.inkMuted)),
                    const SizedBox(height: AppSpacing.xl),
                    step(RequiredPermission.activity, Icons.directions_walk_rounded, l.permActivityTitle, l.gateActivityWhy),
                    const SizedBox(height: AppSpacing.md),
                    step(RequiredPermission.notifications, Icons.notifications_none_rounded, l.permNotificationsTitle, l.gateNotificationsWhy),
                    const SizedBox(height: AppSpacing.md),
                    step(RequiredPermission.battery, Icons.battery_charging_full_rounded, l.gateBatteryTitle, l.gateBatteryWhy),
                    if (gate.oem.family != null) ...[
                      const SizedBox(height: AppSpacing.md),
                      _AutostartStep(oem: gate.oem, confirmed: gate.autostartConfirmed),
                    ],
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, AppSpacing.sm, AppSpacing.gutter, AppSpacing.lg),
                child: Column(children: [
                  if (!gate.satisfied)
                    Padding(
                      padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.sm),
                      child: Text(l.gateRequiredHint, textAlign: TextAlign.center, style: context.text.bodySmall),
                    ),
                  // The router leaves this page by itself once everything is granted.
                  AppButton(label: l.gateContinue, icon: Icons.arrow_back_rounded, onPressed: gate.satisfied ? controller.refresh : null),
                  AppButton.ghost(label: l.profileLogout, onPressed: () => ref.read(sessionProvider.notifier).signOut()),
                ]),
              ),
            ]);
          },
        ),
      ),
    );
  }
}

class _Step extends StatelessWidget {
  const _Step({required this.icon, required this.title, required this.body, required this.status, required this.onGrant});

  final IconData icon;
  final String title;
  final String body;
  final GateStatus status;
  final VoidCallback onGrant;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final granted = status == GateStatus.granted;
    return AppCard(
      borderColor: granted ? p.green : null,
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(granted ? Icons.check_circle_rounded : icon, color: granted ? p.green : p.inkMuted),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: context.text.titleSmall),
            const SizedBox(height: AppSpacing.xxs),
            Text(body, style: context.text.bodySmall),
            if (!granted) ...[
              const SizedBox(height: AppSpacing.md),
              AppButton.secondary(
                label: status == GateStatus.permanentlyDenied ? l.permOpenSettings : l.permAllow,
                expand: false,
                onPressed: onGrant,
              ),
              if (status == GateStatus.permanentlyDenied)
                Padding(
                  padding: const EdgeInsetsDirectional.only(top: AppSpacing.xs),
                  child: Text(l.permOpenSettingsHint, style: context.text.labelSmall),
                ),
            ],
          ]),
        ),
      ]),
    );
  }
}

class _AutostartStep extends ConsumerStatefulWidget {
  const _AutostartStep({required this.oem, required this.confirmed});

  final OemInfo oem;
  final bool confirmed;

  @override
  ConsumerState<_AutostartStep> createState() => _AutostartStepState();
}

class _AutostartStepState extends ConsumerState<_AutostartStep> {
  bool _opened = false;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final controller = ref.read(permissionGateProvider.notifier);
    final brand = switch (widget.oem.family) {
      'xiaomi' => 'شیائومی',
      'huawei' => 'هواوی / آنر',
      'oppo' => 'اوپو / ریلمی',
      'vivo' => 'ویوو',
      'oneplus' => 'وان‌پلاس',
      'samsung' => 'سامسونگ',
      _ => widget.oem.manufacturer,
    };
    final steps = switch (widget.oem.family) {
      'xiaomi' => l.oemXiaomi,
      'huawei' => l.oemHuawei,
      'oppo' => l.oemOppo,
      'vivo' => l.oemVivo,
      'oneplus' => l.oemOnePlus,
      _ => l.oemSamsung,
    };
    return AppCard(
      borderColor: widget.confirmed ? p.green : null,
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(widget.confirmed ? Icons.check_circle_rounded : Icons.rocket_launch_outlined, color: widget.confirmed ? p.green : p.inkMuted),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(l.gateAutostartTitle(brand), style: context.text.titleSmall),
            const SizedBox(height: AppSpacing.xxs),
            Text(steps, style: context.text.bodySmall),
            if (!widget.confirmed) ...[
              const SizedBox(height: AppSpacing.md),
              Wrap(spacing: AppSpacing.sm, runSpacing: AppSpacing.sm, children: [
                AppButton.secondary(
                  label: l.gateAutostartOpen,
                  expand: false,
                  onPressed: () async {
                    await controller.openAutostart();
                    if (mounted) setState(() => _opened = true);
                  },
                ),
                // Can't be read back from the ROM: confirmed by the user after opening it.
                AppButton.ghost(label: l.gateAutostartDone, onPressed: _opened ? controller.confirmAutostart : null),
              ]),
            ],
          ]),
        ),
      ]),
    );
  }
}
