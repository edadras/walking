import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';

import '../analytics/analytics.dart';
import '../localization/l10n.dart';
import '../theme/app_palette.dart';
import '../theme/tokens.dart';
import '../widgets/app_button.dart';

enum AppPermission { activity, notifications, location, camera }

extension on AppPermission {
  Permission get handler => switch (this) {
        AppPermission.activity => Permission.activityRecognition,
        AppPermission.notifications => Permission.notification,
        AppPermission.location => Permission.locationWhenInUse,
        AppPermission.camera => Permission.camera,
      };

  IconData get icon => switch (this) {
        AppPermission.activity => Icons.directions_walk_rounded,
        AppPermission.notifications => Icons.notifications_none_rounded,
        AppPermission.location => Icons.place_outlined,
        AppPermission.camera => Icons.qr_code_scanner_rounded,
      };
}

/// Permissions are requested one at a time, in context, after a Persian
/// explanation of why — never all at once on first launch.
abstract final class PermissionPrimer {
  static Future<bool> isGranted(AppPermission p) async => (await p.handler.status).isGranted;

  /// Returns true when the permission is (or becomes) granted.
  static Future<bool> ensure(BuildContext context, AppPermission permission) async {
    final status = await permission.handler.status;
    if (status.isGranted || status.isLimited) return true;
    if (!context.mounted) return false;

    final l = context.l10n;
    final (title, body) = switch (permission) {
      AppPermission.activity => (l.permActivityTitle, l.permActivityBody),
      AppPermission.notifications => (l.permNotificationsTitle, l.permNotificationsBody),
      AppPermission.location => (l.permLocationTitle, l.permLocationBody),
      AppPermission.camera => (l.permCameraTitle, l.permCameraBody),
    };

    if (status.isPermanentlyDenied) {
      final open = await _sheet(context, permission.icon, title, '$body\n\n${l.permOpenSettingsHint}', l.permOpenSettings);
      if (open == true) await openAppSettings();
      return false;
    }

    trackFrom(context, 'permission_prompt', {'permission': permission.name});
    final proceed = await _sheet(context, permission.icon, title, body, l.permAllow);
    final granted = proceed == true && (await permission.handler.request()).isGranted;
    if (context.mounted) trackFrom(context, 'permission_result', {'permission': permission.name, 'granted': granted});
    return granted;
  }

  static Future<bool?> _sheet(BuildContext context, IconData icon, String title, String body, String action) =>
      showModalBottomSheet<bool>(
        context: context,
        builder: (c) {
          final p = c.palette;
          return Padding(
            padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, 0, AppSpacing.gutter, AppSpacing.xxl),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(color: p.greenSoft, borderRadius: AppRadius.mdAll),
                  child: Icon(icon, color: p.green),
                ),
                const SizedBox(height: AppSpacing.lg),
                Text(title, style: c.text.headlineSmall),
                const SizedBox(height: AppSpacing.sm),
                Text(body, style: c.text.bodyMedium?.copyWith(color: p.inkMuted)),
                const SizedBox(height: AppSpacing.xxl),
                AppButton(label: action, onPressed: () => Navigator.pop(c, true)),
                const SizedBox(height: AppSpacing.sm),
                Center(child: AppButton.ghost(label: c.l10n.permNotNow, onPressed: () => Navigator.pop(c, false))),
              ],
            ),
          );
        },
      );
}
