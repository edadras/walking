import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_text_field.dart';
import '../../config/data/app_config.dart';
import '../application/session_controller.dart';
import '../data/auth_repository.dart';
import 'phone_page.dart';

class OtpPage extends ConsumerStatefulWidget {
  const OtpPage({super.key, required this.args});

  final OtpArgs args;

  @override
  ConsumerState<OtpPage> createState() => _OtpPageState();
}

class _OtpPageState extends ConsumerState<OtpPage> {
  final _code = TextEditingController();
  final _referral = TextEditingController();
  Timer? _timer;
  late int _resendIn = widget.args.resendIn;
  String? _error;
  bool _loading = false;
  bool _showReferral = false;

  @override
  void initState() {
    super.initState();
    _startTimer();
  }

  void _startTimer() {
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (_resendIn <= 1) t.cancel();
      setState(() => _resendIn = (_resendIn - 1).clamp(0, 9999));
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _code.dispose();
    _referral.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    if (_loading) return;
    final code = _code.text;
    if (code.length < 4) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await ref.read(sessionProvider.notifier).signIn(phone: widget.args.phone, code: code, referralCode: _referral.text.trim());
      // The router redirects to the shell once the session is authenticated.
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.fieldError('code') ?? e.message);
      _code.clear();
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _resend() async {
    try {
      final r = await ref.read(authRepositoryProvider).requestOtp(widget.args.phone);
      setState(() {
        _resendIn = r.resendIn;
        _error = null;
      });
      _startTimer();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final length = ref.watch(configProvider).otpLength;

    return Scaffold(
      appBar: AppBar(),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter, vertical: AppSpacing.lg),
          children: [
            Text(l.authOtpTitle, style: context.text.headlineMedium),
            const SizedBox(height: AppSpacing.sm),
            Text(l.authOtpSubtitle(Fa.digits(widget.args.phone)), style: context.text.bodyMedium?.copyWith(color: p.inkMuted)),
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: TextButton(onPressed: () => context.pop(), child: Text(l.authEditPhone, style: TextStyle(color: p.green))),
            ),
            const SizedBox(height: AppSpacing.lg),
            AppTextField(
              label: l.authOtpLabel,
              controller: _code,
              autofocus: true,
              errorText: _error,
              keyboardType: TextInputType.number,
              textDirection: TextDirection.ltr,
              textAlign: TextAlign.center,
              maxLength: length,
              inputFormatters: const [DigitsOnlyFormatter()],
              style: context.text.headlineSmall?.copyWith(letterSpacing: 12),
              onChanged: (v) {
                if (v.length == length) _verify();
              },
            ),
            const SizedBox(height: AppSpacing.md),
            Center(
              child: _resendIn > 0
                  ? Text(l.authResendIn(Fa.digits(_resendIn)), style: context.text.bodySmall)
                  : TextButton(onPressed: _resend, child: Text(l.authResend, style: TextStyle(color: p.green))),
            ),
            const SizedBox(height: AppSpacing.lg),
            AnimatedCrossFade(
              duration: AppMotion.base,
              crossFadeState: _showReferral ? CrossFadeState.showSecond : CrossFadeState.showFirst,
              firstChild: Align(
                alignment: AlignmentDirectional.centerStart,
                child: TextButton.icon(
                  onPressed: () => setState(() => _showReferral = true),
                  icon: Icon(Icons.add_rounded, size: 18, color: p.inkMuted),
                  label: Text(l.authReferralToggle, style: TextStyle(color: p.inkMuted)),
                ),
              ),
              secondChild: AppTextField(label: l.authReferralLabel, controller: _referral, textDirection: TextDirection.ltr, maxLength: 12),
            ),
            const SizedBox(height: AppSpacing.xxl),
            AppButton(label: l.authVerify, onPressed: _verify, loading: _loading),
          ],
        ),
      ),
    );
  }
}
