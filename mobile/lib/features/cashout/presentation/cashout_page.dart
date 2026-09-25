import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:uuid/uuid.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/app_text_field.dart';
import '../../../core/widgets/state_views.dart';
import '../../wallet/data/wallet_repository.dart';
import '../data/cashout.dart';
import '../data/iranian_id.dart';
import 'sms_confirm_sheet.dart';

/// Points → bank account: SMS-confirmed identity, Sheba, then a request within the limits.
class CashoutPage extends ConsumerWidget {
  const CashoutPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final overview = ref.watch(cashoutProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.cashoutTitle)),
      body: AsyncView(
        value: overview,
        onRetry: () => ref.invalidate(cashoutProvider),
        data: (o) => RefreshIndicator(
          color: context.palette.green,
          onRefresh: () async => ref.invalidate(cashoutProvider),
          child: ListView(
            padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter, vertical: AppSpacing.sm),
            children: [
              _Summary(o: o),
              const SizedBox(height: AppSpacing.lg),
              _Step(
                n: 1,
                title: l.cashoutStepPhone,
                done: true,
                child: Text(l.cashoutStepPhoneBody(Fa.digits(o.phone)), style: context.text.bodySmall),
              ),
              _IdentityStep(o: o),
              _BankStep(o: o),
              _RequestStep(o: o),
              SectionHeader(title: l.cashoutHistory),
              if (o.requests.isEmpty)
                EmptyView(title: l.cashoutHistoryEmpty, compact: true)
              else
                for (final r in o.requests) _RequestRow(r: r),
              const SizedBox(height: AppSpacing.xxl),
            ],
          ),
        ),
      ),
    );
  }
}

/// Refreshes everything the payout touches after a successful write.
void _refresh(WidgetRef ref) {
  ref.invalidate(cashoutProvider);
  ref.invalidate(walletBalanceProvider);
}

Future<void> _guard(BuildContext context, Future<void> Function() fn) async {
  try {
    await fn();
  } on ApiException catch (e) {
    if (context.mounted) showAppSnack(context, e.message);
  }
}

class _Summary extends StatelessWidget {
  const _Summary({required this.o});

  final CashoutOverview o;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    return AppCard(
      padding: const EdgeInsetsDirectional.all(AppSpacing.xl),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(l.cashoutAvailable, style: context.text.labelMedium),
        const SizedBox(height: AppSpacing.xs),
        Row(crossAxisAlignment: CrossAxisAlignment.baseline, textBaseline: TextBaseline.alphabetic, children: [
          Text(Fa.number(o.withdrawablePoints), style: context.text.displaySmall),
          const SizedBox(width: AppSpacing.xs),
          Text(l.pointsUnit, style: context.text.bodyMedium?.copyWith(color: p.inkMuted)),
        ]),
        Text(Fa.rial(o.withdrawablePoints * o.rialPerPoint), style: context.text.bodyMedium?.copyWith(color: p.goldInk, fontWeight: FontWeight.w600)),
        const SizedBox(height: AppSpacing.sm),
        Text(l.cashoutTotalBalance(Fa.number(o.availablePoints)), style: context.text.bodySmall),
        if (o.immaturePoints > 0) Text(l.cashoutImmature(Fa.number(o.immaturePoints), Fa.number(o.maturityDays)), style: context.text.bodySmall),
        if (o.availablePoints > o.withdrawablePoints) Text(l.cashoutStoreOnly, style: context.text.bodySmall?.copyWith(color: p.inkMuted)),
        const Padding(padding: EdgeInsets.symmetric(vertical: AppSpacing.md), child: Divider()),
        Text(l.cashoutLimits(Fa.number(o.limits.min), Fa.number(o.limits.max)), style: context.text.bodySmall),
        const SizedBox(height: AppSpacing.xs),
        Text(l.cashoutWindow(Fa.number(o.limits.windowMax), Fa.number(o.limits.windowLeft)), style: context.text.bodySmall),
        const SizedBox(height: AppSpacing.xs),
        Text(l.walletRate(Fa.rial(o.rialPerPoint)), style: context.text.labelSmall),
      ]),
    );
  }
}

class _Step extends StatelessWidget {
  const _Step({required this.n, required this.title, required this.child, this.done = false, this.status});

  final int n;
  final String title;
  final Widget child;
  final bool done;
  final String? status;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Padding(
      padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.md),
      child: AppCard(
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Container(
            width: 28,
            height: 28,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: done ? p.green : p.surfaceSunken, shape: BoxShape.circle),
            child: done
                ? const Icon(Icons.check, size: 16, color: Colors.white)
                : Text(Fa.digits(n), style: context.text.labelMedium?.copyWith(color: p.inkMuted)),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Row(children: [
                Expanded(child: Text(title, style: context.text.titleMedium)),
                if (status != null) StatusChip(status: status!),
              ]),
              const SizedBox(height: AppSpacing.sm),
              child,
            ]),
          ),
        ]),
      ),
    );
  }
}

/// pending | verified | rejected, plus request states (approved / paid / cancelled).
class StatusChip extends StatelessWidget {
  const StatusChip({super.key, required this.status, this.label});

  final String status;
  final String? label;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final (bg, fg) = switch (status) {
      'verified' || 'paid' => (p.greenSoft, p.greenStrong),
      'rejected' => (p.dangerSoft, p.danger),
      'cancelled' => (p.surfaceSunken, p.inkMuted),
      _ => (p.goldSoft, p.goldInk),
    };
    final text = label ??
        switch (status) {
          'verified' => l.cashoutStatusVerified,
          'rejected' => l.cashoutStatusRejected,
          _ => l.cashoutStatusPending,
        };
    return Container(
      padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.sm, vertical: 2),
      decoration: BoxDecoration(color: bg, borderRadius: AppRadius.smAll),
      child: Text(text, style: context.text.labelSmall?.copyWith(color: fg, fontWeight: FontWeight.w600)),
    );
  }
}

class _IdentityStep extends ConsumerWidget {
  const _IdentityStep({required this.o});

  final CashoutOverview o;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final id = o.identity;
    return _Step(
      n: 2,
      title: l.cashoutStepIdentity,
      done: id?.status == 'verified',
      status: id?.status,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        if (id == null)
          Text(l.cashoutStepIdentityEmpty, style: context.text.bodySmall)
        else ...[
          Text(id.fullName, style: context.text.bodyMedium),
          Text('${l.cashoutNationalCode}: ${Fa.digits(id.nationalCode)} · ${FaDate.long(id.birthDate)}', style: context.text.bodySmall),
          if (id.rejectionReason != null) ...[
            const SizedBox(height: AppSpacing.xs),
            Text(id.rejectionReason!, style: context.text.bodySmall?.copyWith(color: context.palette.danger)),
          ],
        ],
        if (o.identityEditable) ...[
          const SizedBox(height: AppSpacing.md),
          AppButton.secondary(
            label: id == null ? l.cashoutIdentitySubmit : l.cashoutIdentityFix,
            onPressed: () async {
              final ok = await context.push<bool>('/cashout/identity', extra: o);
              if (ok == true) _refresh(ref);
            },
          ),
        ],
      ]),
    );
  }
}

class _BankStep extends ConsumerWidget {
  const _BankStep({required this.o});

  final CashoutOverview o;

  Future<void> _add(BuildContext context, WidgetRef ref) async {
    final sheba = await showModalBottomSheet<String>(context: context, isScrollControlled: true, builder: (_) => _ShebaSheet(holder: o.identity!.fullName));
    if (sheba == null || !context.mounted) return;
    await _guard(context, () async {
      final ok = await confirmWithSms(context, phone: o.phone, action: (code) => ref.read(cashoutRepositoryProvider).addBankAccount(sheba: sheba, code: code));
      if (ok == true) _refresh(ref);
    });
  }

  Future<void> _remove(BuildContext context, WidgetRef ref, BankAccount a) async {
    final l = context.l10n;
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        content: Text(l.cashoutRemoveBankConfirm),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.commonCancel)),
          TextButton(onPressed: () => Navigator.pop(c, true), child: Text(l.commonConfirm)),
        ],
      ),
    );
    if (ok != true || !context.mounted) return;
    await _guard(context, () async {
      await ref.read(cashoutRepositoryProvider).removeBankAccount(a.id);
      _refresh(ref);
    });
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    return _Step(
      n: 3,
      title: l.cashoutStepBank,
      done: o.verifiedAccounts.isNotEmpty,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        if (o.accounts.isEmpty) Text(l.cashoutStepBankEmpty, style: context.text.bodySmall),
        for (final a in o.accounts)
          Padding(
            padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.sm),
            child: Row(children: [
              Icon(Icons.account_balance_outlined, size: 20, color: p.inkMuted),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Wrap(spacing: AppSpacing.sm, crossAxisAlignment: WrapCrossAlignment.center, children: [
                    Text(a.bankName, style: context.text.bodyMedium),
                    StatusChip(status: a.status),
                  ]),
                  Directionality(textDirection: TextDirection.ltr, child: Text(a.iban, style: context.text.bodySmall)),
                  if (a.rejectionReason != null) Text(a.rejectionReason!, style: context.text.bodySmall?.copyWith(color: p.danger)),
                ]),
              ),
              IconButton(
                tooltip: l.cashoutRemoveBank,
                icon: Icon(Icons.delete_outline, color: p.inkMuted),
                onPressed: () => _remove(context, ref, a),
              ),
            ]),
          ),
        if (o.identityReady && o.accounts.length < 3) ...[
          const SizedBox(height: AppSpacing.xs),
          AppButton.secondary(label: l.cashoutAddBank, icon: Icons.add, onPressed: () => _add(context, ref)),
        ],
      ]),
    );
  }
}

class _ShebaSheet extends StatefulWidget {
  const _ShebaSheet({required this.holder});

  final String holder;

  @override
  State<_ShebaSheet> createState() => _ShebaSheetState();
}

class _ShebaSheetState extends State<_ShebaSheet> {
  final _c = TextEditingController();
  String? _error;

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  void _submit() {
    final v = IranianId.sheba(_c.text);
    if (v == null) {
      setState(() => _error = context.l10n.cashoutShebaInvalid);
      return;
    }
    Navigator.pop(context, v);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Padding(
      padding: EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, 0, AppSpacing.gutter, AppSpacing.xxl + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text(l.cashoutAddBank, style: context.text.headlineSmall),
        const SizedBox(height: AppSpacing.sm),
        Text(l.cashoutShebaNote(widget.holder), style: context.text.bodySmall),
        const SizedBox(height: AppSpacing.lg),
        AppTextField(
          label: l.cashoutSheba,
          hint: l.cashoutShebaHint,
          controller: _c,
          autofocus: true,
          errorText: _error,
          keyboardType: TextInputType.number,
          textDirection: TextDirection.ltr,
          maxLength: 24,
          inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9۰-۹]'))],
          suffix: const Padding(padding: EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.md), child: Text('IR')),
          onSubmitted: (_) => _submit(),
        ),
        const SizedBox(height: AppSpacing.md),
        AppButton(label: l.commonConfirm, onPressed: _submit),
      ]),
    );
  }
}

class _RequestStep extends ConsumerStatefulWidget {
  const _RequestStep({required this.o});

  final CashoutOverview o;

  @override
  ConsumerState<_RequestStep> createState() => _RequestStepState();
}

class _RequestStepState extends ConsumerState<_RequestStep> {
  final _amount = TextEditingController();
  String? _accountId;
  String? _error;
  // One key per attempt: a retried submit can't create a second request.
  String _key = const Uuid().v4();

  @override
  void dispose() {
    _amount.dispose();
    super.dispose();
  }

  int get _points => int.tryParse(Fa.toLatin(_amount.text)) ?? 0;

  Future<void> _submit() async {
    final o = widget.o;
    final l = context.l10n;
    final max = o.maxRequestable;
    if (_points < o.limits.min || _points > max) {
      setState(() => _error = l.cashoutAmountRange(Fa.number(o.limits.min), Fa.number(max)));
      return;
    }
    setState(() => _error = null);
    final account = _accountId ?? o.verifiedAccounts.first.id;
    await _guard(context, () async {
      final ok = await confirmWithSms(context,
          phone: o.phone,
          action: (code) => ref.read(cashoutRepositoryProvider).request(bankAccountId: account, points: _points, code: code, idempotencyKey: _key));
      if (ok != true || !mounted) return;
      _key = const Uuid().v4();
      _amount.clear();
      _refresh(ref);
      showAppSnack(context, l.cashoutSubmitted);
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final o = widget.o;
    return _Step(
      n: 4,
      title: l.cashoutStepRequest,
      child: !o.canRequest
          ? Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text(l.cashoutNotYet, style: context.text.labelMedium),
              const SizedBox(height: AppSpacing.xs),
              for (final b in o.blockers)
                Padding(
                  padding: const EdgeInsetsDirectional.only(top: AppSpacing.xs),
                  child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Icon(Icons.info_outline, size: 16, color: p.goldInk),
                    const SizedBox(width: AppSpacing.xs),
                    Expanded(child: Text(b.message, style: context.text.bodySmall)),
                  ]),
                ),
            ])
          : Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              AppTextField(
                label: l.cashoutAmount,
                controller: _amount,
                errorText: _error,
                keyboardType: TextInputType.number,
                textDirection: TextDirection.ltr,
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9۰-۹]'))],
                onChanged: (_) => setState(() {}),
                suffix: TextButton(
                  onPressed: o.maxRequestable == 0 ? null : () => setState(() => _amount.text = '${o.maxRequestable}'),
                  child: Text(l.cashoutAll),
                ),
              ),
              const SizedBox(height: AppSpacing.xs),
              Text(l.cashoutAmountRial(Fa.rial(_points * o.rialPerPoint)), style: context.text.bodyMedium?.copyWith(color: p.goldInk, fontWeight: FontWeight.w600)),
              const SizedBox(height: AppSpacing.md),
              Text(l.cashoutDestination, style: context.text.labelMedium?.copyWith(color: p.inkMuted)),
              RadioGroup<String>(
                groupValue: _accountId ?? o.verifiedAccounts.first.id,
                onChanged: (v) => setState(() => _accountId = v),
                child: Column(children: [
                  for (final a in o.verifiedAccounts)
                    RadioListTile<String>(
                      contentPadding: EdgeInsets.zero,
                      dense: true,
                      value: a.id,
                      title: Text(a.bankName),
                      subtitle: Directionality(textDirection: TextDirection.ltr, child: Align(alignment: AlignmentDirectional.centerEnd, child: Text(a.iban))),
                    ),
                ]),
              ),
              const SizedBox(height: AppSpacing.sm),
              Text(l.cashoutSubmitNote, style: context.text.bodySmall),
              const SizedBox(height: AppSpacing.md),
              AppButton(label: l.cashoutSubmit, icon: Icons.account_balance_wallet_outlined, onPressed: _submit),
            ]),
    );
  }
}

class _RequestRow extends ConsumerWidget {
  const _RequestRow({required this.r});

  final CashoutRequest r;

  Future<void> _cancel(BuildContext context, WidgetRef ref) async {
    final l = context.l10n;
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        content: Text(l.cashoutCancelConfirm),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.commonCancel)),
          TextButton(onPressed: () => Navigator.pop(c, true), child: Text(l.commonConfirm)),
        ],
      ),
    );
    if (ok != true || !context.mounted) return;
    await _guard(context, () async {
      await ref.read(cashoutRepositoryProvider).cancel(r.id);
      _refresh(ref);
      if (context.mounted) showAppSnack(context, l.cashoutCancelled);
    });
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    return Padding(
      padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.sm),
      child: AppCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(children: [
            Expanded(child: Text(Fa.rial(r.amountRial), style: context.text.titleMedium)),
            StatusChip(status: r.status, label: r.statusLabel),
          ]),
          const SizedBox(height: AppSpacing.xs),
          Text('${Fa.number(r.points)} ${l.pointsUnit} · ${r.bank ?? ''} · ${FaDate.long(r.createdAt)}', style: context.text.bodySmall),
          if (r.status == 'pending' && r.queuePosition != null) Text(l.cashoutQueue(Fa.number(r.queuePosition!)), style: context.text.bodySmall?.copyWith(color: p.goldInk)),
          if (r.bankReference != null) Text(l.cashoutReference(r.bankReference!), style: context.text.bodySmall?.copyWith(color: p.greenStrong)),
          if (r.rejectionReason != null && r.status != 'cancelled') Text(r.rejectionReason!, style: context.text.bodySmall?.copyWith(color: p.danger)),
          if (r.status == 'pending')
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: TextButton(onPressed: () => _cancel(context, ref), child: Text(l.cashoutCancel, style: TextStyle(color: p.danger))),
            ),
        ]),
      ),
    );
  }
}
