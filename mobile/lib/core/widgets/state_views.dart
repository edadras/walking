import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../localization/l10n.dart';
import '../network/api_exception.dart';
import '../theme/app_palette.dart';
import '../theme/tokens.dart';
import 'app_button.dart';
import 'skeleton.dart';
import 'trail.dart';

/// Centered brand loader with an optional caption.
class LoadingView extends StatelessWidget {
  const LoadingView({super.key, this.label});

  final String? label;

  @override
  Widget build(BuildContext context) => Center(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const TrailLoader(),
          if (label != null) ...[const SizedBox(height: AppSpacing.md), Text(label!, style: context.text.bodySmall)],
        ]),
      );
}

class EmptyView extends StatelessWidget {
  const EmptyView({super.key, required this.title, this.message, this.actionLabel, this.onAction, this.compact = false});

  final String title;
  final String? message;
  final String? actionLabel;
  final VoidCallback? onAction;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final t = context.text;
    return Padding(
      padding: EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.x3, vertical: compact ? AppSpacing.xxl : AppSpacing.x4),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Trail(progress: 0, dots: 9, height: 44, width: 160, showAccent: false),
          const SizedBox(height: AppSpacing.xl),
          Text(title, style: t.titleMedium, textAlign: TextAlign.center),
          if (message != null) ...[
            const SizedBox(height: AppSpacing.sm),
            Text(message!, style: t.bodyMedium?.copyWith(color: context.palette.inkMuted), textAlign: TextAlign.center),
          ],
          if (actionLabel != null && onAction != null) ...[
            const SizedBox(height: AppSpacing.xl),
            AppButton(label: actionLabel!, onPressed: onAction, expand: false),
          ],
        ],
      ),
    );
  }
}

class ErrorView extends StatelessWidget {
  const ErrorView({super.key, required this.error, this.onRetry, this.compact = false});

  final Object error;
  final VoidCallback? onRetry;
  final bool compact;

  static String messageOf(Object error) => error is ApiException ? error.message : ApiException.unknown.message;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final isNetwork = error is ApiException && (error as ApiException).isNetwork;
    return Padding(
      padding: EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.x3, vertical: compact ? AppSpacing.xl : AppSpacing.x4),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(isNetwork ? Icons.wifi_off_rounded : Icons.error_outline_rounded, color: p.inkSubtle, size: 32),
          const SizedBox(height: AppSpacing.md),
          Text(messageOf(error), style: context.text.bodyMedium?.copyWith(color: p.inkMuted), textAlign: TextAlign.center),
          if (onRetry != null) ...[
            const SizedBox(height: AppSpacing.lg),
            AppButton.secondary(label: context.l10n.commonRetry, onPressed: onRetry, icon: Icons.refresh_rounded, expand: false),
          ],
        ],
      ),
    );
  }
}

/// Renders an [AsyncValue] with a page-specific skeleton, a retry-able error
/// state, and keeps showing stale data while refreshing.
class AsyncView<T> extends StatelessWidget {
  const AsyncView({super.key, required this.value, required this.data, this.skeleton, this.onRetry});

  final AsyncValue<T> value;
  final Widget Function(T data) data;
  final Widget? skeleton;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return AnimatedSwitcher(
      duration: AppMotion.base,
      child: switch (value) {
        // `value as T` (not `value?`): a loaded null is data for nullable T (e.g. "not in an organization").
        AsyncValue(:final value, hasValue: true) => KeyedSubtree(key: const ValueKey('data'), child: data(value as T)),
        AsyncValue(:final error?) => KeyedSubtree(key: const ValueKey('error'), child: Center(child: ErrorView(error: error, onRetry: onRetry))),
        _ => KeyedSubtree(key: const ValueKey('loading'), child: skeleton ?? const SkeletonList()),
      },
    );
  }
}

void showAppSnack(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}
