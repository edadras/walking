import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/dates.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/state_views.dart';
import '../data/profile_repository.dart';

class DevicesPage extends ConsumerWidget {
  const DevicesPage({super.key});

  Future<void> _revoke(BuildContext context, WidgetRef ref, UserDevice d) async {
    final l = context.l10n;
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        content: Text(l.devicesRevokeConfirm),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.commonCancel)),
          TextButton(onPressed: () => Navigator.pop(c, true), child: Text(l.devicesRevoke)),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(profileRepositoryProvider).revokeDevice(d.id);
      ref.invalidate(devicesProvider);
    } on ApiException catch (e) {
      if (context.mounted) showAppSnack(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    return Scaffold(
      appBar: AppBar(title: Text(l.devicesTitle)),
      body: AsyncView(
        value: ref.watch(devicesProvider),
        onRetry: () => ref.invalidate(devicesProvider),
        data: (devices) => ListView(
          children: [
            for (final d in devices)
              ListTile(
                leading: const Icon(Icons.smartphone_rounded),
                title: Text(d.model ?? 'Android'),
                subtitle: Text(d.isCurrent ? l.devicesCurrent : l.devicesLastSeen(d.lastSeenAt != null ? FaDate.relative(d.lastSeenAt!) : '—')),
                trailing: d.isCurrent
                    ? Icon(Icons.check_circle_rounded, color: p.green)
                    : TextButton(onPressed: () => _revoke(context, ref, d), child: Text(l.devicesRevoke, style: TextStyle(color: p.danger))),
              ),
            if (devices.length <= 1)
              Padding(
                padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
                child: Text(l.devicesEmpty, style: context.text.bodySmall),
              ),
          ],
        ),
      ),
    );
  }
}
