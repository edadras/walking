import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/analytics/analytics.dart';
import '../core/platform/home_widget.dart';
import '../features/auth/application/session_controller.dart';

/// Side effects that follow sign-in/out and don't belong to any one screen.
final sessionEffectsProvider = Provider<void>((ref) {
  ref.listen(sessionProvider, (prev, next) {
    final wasIn = prev?.value is SessionAuthenticated;
    final isIn = next.value is SessionAuthenticated;
    if (wasIn == isIn) return;
    final analytics = ref.read(analyticsProvider);
    analytics.enabled = isIn;
    if (isIn) analytics.track('app_open');
    if (wasIn && !isIn) ref.read(homeWidgetProvider).clear();
  }, fireImmediately: true);
});
