import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shamsi_date/shamsi_date.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_text_field.dart';
import '../../../core/widgets/state_views.dart';
import '../data/cashout.dart';
import '../data/iranian_id.dart';
import 'sms_confirm_sheet.dart';

/// Payout identity: must match the national card; support verifies it before any payout.
class CashoutIdentityPage extends ConsumerStatefulWidget {
  const CashoutIdentityPage({super.key, required this.overview});

  final CashoutOverview overview;

  @override
  ConsumerState<CashoutIdentityPage> createState() => _CashoutIdentityPageState();
}

class _CashoutIdentityPageState extends ConsumerState<CashoutIdentityPage> {
  late final _first = TextEditingController(text: widget.overview.identity?.firstName);
  late final _last = TextEditingController(text: widget.overview.identity?.lastName);
  final _code = TextEditingController();
  int? _year;
  int? _month;
  int? _day;
  final _errors = <String, String?>{};

  @override
  void initState() {
    super.initState();
    final b = widget.overview.identity?.birthDate;
    if (b != null) {
      final j = Jalali.fromDateTime(b);
      _year = j.year;
      _month = j.month;
      _day = j.day;
    }
  }

  @override
  void dispose() {
    _first.dispose();
    _last.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final l = context.l10n;
    final code = IranianId.nationalCode(_code.text);
    setState(() {
      _errors
        ..['first'] = IranianId.persianName(_first.text) ? null : l.cashoutNameHint
        ..['last'] = IranianId.persianName(_last.text) ? null : l.cashoutNameHint
        ..['code'] = code == null ? l.cashoutNationalCodeInvalid : null
        ..['birth'] = _year == null || _month == null || _day == null ? l.cashoutRequired : null;
    });
    if (_errors.values.any((e) => e != null)) return;
    final birth = Jalali(_year!, _month!, _day!.clamp(1, Jalali(_year!, _month!).monthLength)).toDateTime();

    // The SMS sheet shows its own progress.
    try {
      final ok = await confirmWithSms(context,
          phone: widget.overview.phone,
          action: (sms) => ref.read(cashoutRepositoryProvider).submitIdentity(firstName: _first.text, lastName: _last.text, nationalCode: code!, birthDate: birth, code: sms));
      if (ok == true && mounted) context.pop(true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _errors['first'] = e.fieldError('first_name');
        _errors['last'] = e.fieldError('last_name');
        _errors['code'] = e.code.startsWith('national_code') ? e.message : e.fieldError('national_code');
      });
      showAppSnack(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final nowYear = Jalali.now().year;
    Widget dropdown(String label, int? value, List<int> items, String Function(int) text, ValueChanged<int?> onChanged) => Expanded(
          child: DropdownButtonFormField<int>(
            initialValue: value,
            isExpanded: true,
            decoration: InputDecoration(labelText: label),
            items: [for (final i in items) DropdownMenuItem(value: i, child: Text(text(i)))],
            onChanged: onChanged,
          ),
        );

    return Scaffold(
      appBar: AppBar(title: Text(l.cashoutStepIdentity)),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter, vertical: AppSpacing.lg),
          children: [
            if (widget.overview.identity?.rejectionReason != null) ...[
              Text(widget.overview.identity!.rejectionReason!, style: context.text.bodyMedium?.copyWith(color: p.danger)),
              const SizedBox(height: AppSpacing.lg),
            ],
            AppTextField(label: l.cashoutFirstName, hint: l.cashoutNameHint, controller: _first, errorText: _errors['first'], maxLength: 50),
            const SizedBox(height: AppSpacing.md),
            AppTextField(label: l.cashoutLastName, hint: l.cashoutNameHint, controller: _last, errorText: _errors['last'], maxLength: 60),
            const SizedBox(height: AppSpacing.md),
            AppTextField(
              label: l.cashoutNationalCode,
              controller: _code,
              errorText: _errors['code'],
              keyboardType: TextInputType.number,
              textDirection: TextDirection.ltr,
              maxLength: 10,
              inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9۰-۹]'))],
            ),
            const SizedBox(height: AppSpacing.md),
            Text(l.cashoutBirthDate, style: context.text.labelMedium?.copyWith(color: p.inkMuted)),
            const SizedBox(height: AppSpacing.sm),
            Row(children: [
              dropdown(l.cashoutDay, _day, [for (var d = 1; d <= 31; d++) d], Fa.digits, (v) => setState(() => _day = v)),
              const SizedBox(width: AppSpacing.sm),
              dropdown(l.cashoutMonth, _month, [for (var m = 1; m <= 12; m++) m], (m) => FaDate.months[m - 1], (v) => setState(() => _month = v)),
              const SizedBox(width: AppSpacing.sm),
              dropdown(l.cashoutYear, _year, [for (var y = nowYear - 10; y >= nowYear - 100; y--) y], Fa.digits, (v) => setState(() => _year = v)),
            ]),
            if (_errors['birth'] != null)
              Padding(
                padding: const EdgeInsetsDirectional.only(top: AppSpacing.xs),
                child: Text(_errors['birth']!, style: context.text.bodySmall?.copyWith(color: p.danger)),
              ),
            const SizedBox(height: AppSpacing.lg),
            Text(l.cashoutIdentityNote, style: context.text.bodySmall),
            const SizedBox(height: AppSpacing.xl),
            AppButton(label: l.cashoutIdentitySubmit, onPressed: _submit),
          ],
        ),
      ),
    );
  }
}
