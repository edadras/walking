import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_text_field.dart';
import '../../../core/widgets/trail.dart';
import '../data/auth_repository.dart';

/// Iranian mobile validation (09XXXXXXXXX), same rule as the server.
String? normalizeIranMobile(String input) {
  var d = input.replaceAll(RegExp(r'\D'), '');
  if (d.startsWith('0098')) d = d.substring(4);
  if (d.startsWith('98') && d.length == 12) d = d.substring(2);
  if (d.startsWith('0') && d.length == 11) d = d.substring(1);
  return RegExp(r'^9\d{9}$').hasMatch(d) ? '0$d' : null;
}

class PhonePage extends ConsumerStatefulWidget {
  const PhonePage({super.key});

  @override
  ConsumerState<PhonePage> createState() => _PhonePageState();
}

class _PhonePageState extends ConsumerState<PhonePage> {
  final _phone = TextEditingController();
  String? _error;
  bool _loading = false;

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final l = context.l10n;
    final phone = normalizeIranMobile(_phone.text);
    if (phone == null) {
      setState(() => _error = l.authPhoneInvalid);
      return;
    }
    setState(() {
      _error = null;
      _loading = true;
    });
    try {
      final result = await ref.read(authRepositoryProvider).requestOtp(phone);
      if (!mounted) return;
      context.push('/auth/otp', extra: OtpArgs(phone: phone, resendIn: result.resendIn));
    } on ApiException catch (e) {
      setState(() => _error = e.fieldError('phone') ?? e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter, vertical: AppSpacing.x4),
          children: [
            const Trail(progress: 0.25, dots: 12, height: 40, width: 150),
            const SizedBox(height: AppSpacing.x3),
            Text(l.authPhoneTitle, style: context.text.headlineMedium),
            const SizedBox(height: AppSpacing.sm),
            Text(l.authPhoneSubtitle, style: context.text.bodyMedium?.copyWith(color: p.inkMuted)),
            const SizedBox(height: AppSpacing.x3),
            AppTextField(
              label: l.authPhoneLabel,
              controller: _phone,
              hint: l.authPhoneHint,
              autofocus: true,
              errorText: _error,
              keyboardType: TextInputType.phone,
              textDirection: TextDirection.ltr,
              textAlign: TextAlign.left,
              maxLength: 14,
              inputFormatters: const [DigitsOnlyFormatter()],
              onSubmitted: (_) => _submit(),
              style: context.text.titleMedium?.copyWith(letterSpacing: 1.5),
            ),
            const SizedBox(height: AppSpacing.xxl),
            AppButton(label: l.authSendCode, onPressed: _submit, loading: _loading),
            const SizedBox(height: AppSpacing.lg),
            Wrap(
              alignment: WrapAlignment.center,
              children: [
                Text(l.authTermsNote, style: context.text.bodySmall, textAlign: TextAlign.center),
                TextButton(
                  onPressed: () => context.push('/page/terms'),
                  child: Text(l.authTermsLink, style: context.text.bodySmall?.copyWith(color: p.green, fontWeight: FontWeight.w600)),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class OtpArgs {
  const OtpArgs({required this.phone, required this.resendIn});

  final String phone;
  final int resendIn;
}
