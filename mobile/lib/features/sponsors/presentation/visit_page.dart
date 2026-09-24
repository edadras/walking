import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/permissions/permission_primer.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/state_views.dart';
import '../../activity/application/activity_providers.dart';
import '../application/location_source.dart';
import '../data/sponsor_models.dart';
import '../data/sponsor_repository.dart';
import 'sponsor_widgets.dart';

/// Live visit. While this page is visible (foreground only) it sends the
/// current position every `ping_interval_s`; the server measures the stay.
class VisitPage extends ConsumerStatefulWidget {
  const VisitPage({super.key, required this.id});

  final String id;

  @override
  ConsumerState<VisitPage> createState() => _VisitPageState();
}

class _VisitPageState extends ConsumerState<VisitPage> with WidgetsBindingObserver {
  VisitState? _visit;
  Object? _error;
  Timer? _timer;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // No background location: pinging stops when the app is not visible.
    if (state == AppLifecycleState.resumed) {
      _schedule();
      _ping();
    } else {
      _timer?.cancel();
    }
  }

  Future<void> _load() async {
    try {
      _apply(await ref.read(sponsorRepositoryProvider).visit(widget.id));
    } catch (e) {
      if (mounted) setState(() => _error = e);
    }
  }

  void _apply(VisitState v) {
    if (!mounted) return;
    final wasOpen = _visit?.open ?? true;
    setState(() {
      _visit = v;
      _error = null;
    });
    if (v.open) {
      _schedule();
    } else {
      _timer?.cancel();
      if (wasOpen && v.rewarded) {
        ref.invalidate(homeProvider);
      }
    }
  }

  void _schedule() {
    final v = _visit;
    if (v == null || !v.open || (_timer?.isActive ?? false)) return;
    _timer = Timer.periodic(Duration(seconds: v.pingIntervalS), (_) => _ping());
  }

  Future<void> _ping() async {
    final v = _visit;
    if (v == null || !v.open || _busy) return;
    _busy = true;
    try {
      final fix = await ref.read(locationSourceProvider).current();
      _apply(await ref.read(sponsorRepositoryProvider).ping(v.id, fix));
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    } on LocationUnavailable {
      // Shown as "outside" until location comes back.
    } finally {
      _busy = false;
    }
  }

  Future<void> _scan() async {
    if (!await PermissionPrimer.ensure(context, AppPermission.camera)) return;
    if (!mounted) return;
    final token = await context.push<String>('/scan-qr');
    if (token == null || !mounted) return;
    try {
      _apply(await ref.read(sponsorRepositoryProvider).submitQr(widget.id, token));
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final v = _visit;
    return Scaffold(
      appBar: AppBar(title: Text(v?.locationName ?? l.visitTitle)),
      body: switch ((v, _error)) {
        (null, null) => const LoadingView(),
        (null, final Object e) => ErrorView(error: e, onRetry: _load),
        (final VisitState v, _) => v.open ? _Live(visit: v, onScan: _scan) : _Result(visit: v),
      },
    );
  }
}

class _Live extends StatelessWidget {
  const _Live({required this.visit, required this.onScan});

  final VisitState visit;
  final VoidCallback onScan;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final v = visit;
    return ListView(
      padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
      children: [
        Text(v.campaignName, style: context.text.titleMedium, textAlign: TextAlign.center),
        const SizedBox(height: AppSpacing.xl),
        Center(
          child: Semantics(
            label: l.visitStay(FaDate.clock(Duration(seconds: v.staySeconds)), FaDate.clock(Duration(seconds: v.minStaySeconds))),
            child: SizedBox(
              width: 200,
              height: 200,
              child: Stack(alignment: Alignment.center, children: [
                SizedBox.expand(child: CircularProgressIndicator(value: v.stayFraction, strokeWidth: 10, color: p.green, backgroundColor: p.surfaceSunken)),
                Column(mainAxisSize: MainAxisSize.min, children: [
                  Text(FaDate.clock(Duration(seconds: v.staySeconds)), style: context.text.displaySmall),
                  Text(l.visitOfTotal(FaDate.clock(Duration(seconds: v.minStaySeconds))), style: context.text.bodySmall),
                ]),
              ]),
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.xl),
        AppCard(
          color: v.inside ? p.greenSoft : p.surfaceSunken,
          borderColor: v.inside ? p.greenSoft : p.border,
          child: Row(children: [
            Icon(v.inside ? Icons.check_circle_rounded : Icons.near_me_disabled_outlined, color: v.inside ? p.green : p.inkSubtle),
            const SizedBox(width: AppSpacing.sm),
            Expanded(child: Text(v.inside ? l.visitInside : l.visitOutside, style: context.text.titleSmall)),
          ]),
        ),
        const SizedBox(height: AppSpacing.md),
        if (v.requiresQr)
          v.qrVerified
              ? AppCard(child: Row(children: [Icon(Icons.qr_code_2_rounded, color: p.green), const SizedBox(width: AppSpacing.sm), Text(l.visitQrDone, style: context.text.titleSmall)]))
              : Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  AppButton(label: l.visitScanQr, icon: Icons.qr_code_scanner_rounded, onPressed: v.inside ? onScan : null),
                  const SizedBox(height: AppSpacing.xs),
                  Text(l.visitQrHint, style: context.text.bodySmall, textAlign: TextAlign.center),
                ]),
        const SizedBox(height: AppSpacing.xl),
        Text(l.visitKeepOpen, style: context.text.bodySmall?.copyWith(color: p.inkSubtle), textAlign: TextAlign.center),
      ],
    );
  }
}

class _Result extends StatelessWidget {
  const _Result({required this.visit});

  final VisitState visit;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final v = visit;
    final ok = v.rewarded;
    return Padding(
      padding: const EdgeInsetsDirectional.all(AppSpacing.xxl),
      child: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Center(
          child: CircleAvatar(
            radius: 48,
            backgroundColor: ok ? p.greenSoft : p.surfaceSunken,
            child: Icon(ok ? Icons.check_rounded : Icons.info_outline_rounded, size: 52, color: ok ? p.green : p.inkSubtle),
          ),
        ),
        const SizedBox(height: AppSpacing.xl),
        Text(ok ? l.visitRewarded : (v.status == 'expired' ? l.visitExpired : l.visitRejected), style: context.text.headlineSmall, textAlign: TextAlign.center),
        const SizedBox(height: AppSpacing.sm),
        Text(
          ok ? l.visitRewardedBody(Fa.number(v.pointsAwarded)) : reasonLabel(context, v.rejectionReason),
          style: context.text.bodyMedium,
          textAlign: TextAlign.center,
        ),
        if (v.coupon != null) ...[
          const SizedBox(height: AppSpacing.xl),
          AppCard(
            color: p.goldSoft,
            borderColor: p.goldSoft,
            onTap: () => context.push('/coupons'),
            child: Row(children: [
              Icon(Icons.confirmation_number_rounded, color: p.goldInk),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(l.visitCouponReceived, style: context.text.labelMedium),
                  Text(v.coupon!.title, style: context.text.titleSmall?.copyWith(color: p.goldInk)),
                ]),
              ),
            ]),
          ),
        ],
        const SizedBox(height: AppSpacing.xxl),
        AppButton.secondary(label: l.visitBack, onPressed: () => context.pop()),
      ]),
    );
  }
}
