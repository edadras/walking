import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/state_views.dart';
import '../../auth/application/session_controller.dart';
import '../../config/data/app_config.dart';
import '../data/profile_repository.dart';

class DeleteAccountPage extends ConsumerStatefulWidget {
  const DeleteAccountPage({super.key});

  @override
  ConsumerState<DeleteAccountPage> createState() => _DeleteAccountPageState();
}

class _DeleteAccountPageState extends ConsumerState<DeleteAccountPage> {
  bool _busy = false;

  Future<void> _run(Future<void> Function() action) async {
    setState(() => _busy = true);
    try {
      await action();
      await ref.read(sessionProvider.notifier).refreshMe();
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final me = ref.watch(meProvider);
    final grace = ref.watch(configProvider).deletionGraceDays;
    final pending = me.deletionRequestedAt;
    final repo = ref.read(profileRepositoryProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l.deleteAccountTitle)),
      body: ListView(
        padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
        children: [
          Text(l.deleteAccountBody(Fa.digits(grace)), style: context.text.bodyLarge?.copyWith(color: p.inkMuted)),
          const SizedBox(height: AppSpacing.x3),
          if (pending != null) ...[
            Text(l.deleteAccountPending(FaDate.long(pending.add(Duration(days: grace)))), style: context.text.titleSmall?.copyWith(color: p.danger)),
            const SizedBox(height: AppSpacing.lg),
            AppButton(label: l.deleteAccountCancel, onPressed: () => _run(repo.cancelDeletion), loading: _busy),
          ] else
            AppButton(
              label: l.deleteAccountConfirm,
              variant: AppButtonVariant.danger,
              loading: _busy,
              onPressed: () => _run(() async => repo.requestDeletion()),
            ),
        ],
      ),
    );
  }
}
