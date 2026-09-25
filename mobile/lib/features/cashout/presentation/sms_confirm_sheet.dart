import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_text_field.dart';
import '../../config/data/app_config.dart';
import '../data/cashout.dart';

/// Sends a cash-out SMS code, collects it and runs [action] with it.
///
/// Returns true when [action] succeeded, null when dismissed. A wrong or expired
/// code is shown in the sheet; any other server error closes it and is rethrown.
Future<bool?> confirmWithSms(BuildContext context, {required String phone, required Future<void> Function(String code) action}) async {
  final result = await showModalBottomSheet<Object>(
    context: context,
    isScrollControlled: true,
    builder: (_) => _SmsSheet(phone: phone, action: action),
  );
  if (result is ApiException) throw result;
  return result == true ? true : null;
}

class _SmsSheet extends ConsumerStatefulWidget {
  const _SmsSheet({required this.phone, required this.action});

  final String phone;
  final Future<void> Function(String code) action;

  @override
  ConsumerState<_SmsSheet> createState() => _SmsSheetState();
}

class _SmsSheetState extends ConsumerState<_SmsSheet> {
  final _code = TextEditingController();
  Timer? _timer;
  int _resendIn = 0;
  bool _sending = true;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _send();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _code.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      final wait = await ref.read(cashoutRepositoryProvider).sendCode();
      if (!mounted) return;
      setState(() => _resendIn = wait);
      _timer?.cancel();
      _timer = Timer.periodic(const Duration(seconds: 1), (t) {
        if (_resendIn <= 1) t.cancel();
        if (mounted) setState(() => _resendIn = (_resendIn - 1).clamp(0, 9999));
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _confirm() async {
    final code = Fa.toLatin(_code.text.trim());
    if (code.length < 4 || _busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.action(code);
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.code.startsWith('otp_') || e.fieldError('code') != null) {
        setState(() => _error = e.fieldError('code') ?? e.message);
        _code.clear();
      } else {
        Navigator.pop(context, e);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final length = ref.watch(configProvider).otpLength;
    return Padding(
      padding: EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, 0, AppSpacing.gutter, AppSpacing.xxl + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            width: 44,
            height: 44,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: p.greenSoft, borderRadius: AppRadius.mdAll),
            child: Icon(Icons.sms_outlined, color: p.green),
          ),
          const SizedBox(height: AppSpacing.lg),
          Text(l.cashoutOtpTitle, style: context.text.headlineSmall),
          const SizedBox(height: AppSpacing.sm),
          Text(l.cashoutOtpBody(Fa.digits(widget.phone)), style: context.text.bodyMedium?.copyWith(color: p.inkMuted)),
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
            onChanged: (v) {
              if (v.length == length) _confirm();
            },
          ),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: TextButton(
              onPressed: _sending || _resendIn > 0 ? null : _send,
              child: Text(_resendIn > 0 ? l.cashoutOtpResendIn(Fa.number(_resendIn)) : l.cashoutOtpResend),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          AppButton(label: l.cashoutOtpConfirm, loading: _busy, onPressed: _sending ? null : _confirm),
        ],
      ),
    );
  }
}
