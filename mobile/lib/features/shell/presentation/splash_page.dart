import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/state_views.dart';
import '../../../core/widgets/trail.dart';
import '../../auth/application/session_controller.dart';

/// Shown while the session resolves. On failure (e.g. offline at launch)
/// offers a retry instead of a dead end.
class SplashPage extends ConsumerWidget {
  const SplashPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);
    final p = context.palette;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(context.l10n.appName, style: context.text.displaySmall?.copyWith(color: p.green)),
              const SizedBox(height: AppSpacing.xl),
              if (session.hasError)
                ErrorView(error: session.error!, onRetry: () => ref.read(sessionProvider.notifier).retry(), compact: true)
              else
                const TrailLoader(),
            ],
          ),
        ),
      ),
    );
  }
}

class BlockedPage extends ConsumerWidget {
  const BlockedPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(sessionProvider).value;
    final l = context.l10n;
    final message = state is SessionBlocked ? state.error.message : '';
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsetsDirectional.all(AppSpacing.x3),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.lock_outline_rounded, color: context.palette.danger, size: 32),
              const SizedBox(height: AppSpacing.lg),
              Text(l.accountBlockedTitle, style: context.text.headlineSmall),
              const SizedBox(height: AppSpacing.sm),
              Text(message, style: context.text.bodyLarge),
              const SizedBox(height: AppSpacing.sm),
              Text(l.accountBlockedContact, style: context.text.bodySmall),
              const SizedBox(height: AppSpacing.x3),
              TextButton(onPressed: () => ref.read(sessionProvider.notifier).signOut(), child: Text(l.profileLogout)),
            ],
          ),
        ),
      ),
    );
  }
}
