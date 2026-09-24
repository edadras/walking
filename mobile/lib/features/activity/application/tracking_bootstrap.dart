import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/permissions/permission_primer.dart';
import 'activity_providers.dart';
import 'tracking_service.dart';

/// Lives inside the authenticated shell. Keeps background tracking scheduled and
/// syncs when the app opens, returns to the foreground, and every few minutes
/// while visible. Nothing here runs for signed-out users.
class TrackingBootstrap extends ConsumerStatefulWidget {
  const TrackingBootstrap({super.key, required this.child});

  final Widget child;

  static const interval = Duration(minutes: 5);

  @override
  ConsumerState<TrackingBootstrap> createState() => _TrackingBootstrapState();
}

class _TrackingBootstrapState extends ConsumerState<TrackingBootstrap> with WidgetsBindingObserver {
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _run();
    _timer = Timer.periodic(TrackingBootstrap.interval, (_) => _run());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _run();
      _timer ??= Timer.periodic(TrackingBootstrap.interval, (_) => _run());
    } else if (state == AppLifecycleState.paused) {
      _timer?.cancel();
      _timer = null;
    }
  }

  Future<void> _run() async {
    try {
      if (await PermissionPrimer.isGranted(AppPermission.activity)) {
        await ref.read(stepPlatformProvider).startPassive();
      }
      final report = await ref.read(trackingServiceProvider).sync();
      if (!mounted) return;
      if (report.enqueued > 0 || report.accepted > 0) {
        ref.invalidate(homeProvider);
        ref.invalidate(dayActivityProvider);
      }
    } catch (_) {
      // Tracking is best-effort in the foreground; data stays queued for next time.
    }
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
