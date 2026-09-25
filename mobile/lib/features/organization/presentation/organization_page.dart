import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

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
import '../data/organization.dart';

/// Company wellness programme: join with the company code, colleagues ranking, company challenges.
class OrganizationPage extends ConsumerWidget {
  const OrganizationPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final org = ref.watch(organizationProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.orgTitle)),
      body: AsyncView(
        value: org,
        onRetry: () => ref.invalidate(organizationProvider),
        data: (o) => o == null ? const _JoinView() : _MemberView(org: o),
      ),
    );
  }
}

class _JoinView extends ConsumerStatefulWidget {
  const _JoinView();

  @override
  ConsumerState<_JoinView> createState() => _JoinViewState();
}

class _JoinViewState extends ConsumerState<_JoinView> {
  final _code = TextEditingController();
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _join() async {
    if (_code.text.trim().length < 4 || _busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(organizationRepositoryProvider).join(_code.text);
      ref.invalidate(organizationProvider);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    return ListView(
      padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter, vertical: AppSpacing.lg),
      children: [
        Container(
          width: 56,
          height: 56,
          decoration: BoxDecoration(color: p.greenSoft, borderRadius: AppRadius.mdAll),
          child: Icon(Icons.apartment_rounded, color: p.green, size: 30),
        ),
        const SizedBox(height: AppSpacing.lg),
        Text(l.orgJoinTitle, style: context.text.headlineSmall),
        const SizedBox(height: AppSpacing.sm),
        Text(l.orgJoinBody, style: context.text.bodyMedium?.copyWith(color: p.inkMuted)),
        const SizedBox(height: AppSpacing.xl),
        AppTextField(
          label: l.orgCode,
          controller: _code,
          errorText: _error,
          textDirection: TextDirection.ltr,
          maxLength: 10,
          onSubmitted: (_) => _join(),
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(l.orgPrivacy, style: context.text.bodySmall),
        const SizedBox(height: AppSpacing.lg),
        AppButton(label: l.orgJoin, loading: _busy, onPressed: _join),
      ],
    );
  }
}

class _MemberView extends ConsumerWidget {
  const _MemberView({required this.org});

  final Organization org;

  Future<void> _leave(BuildContext context, WidgetRef ref) async {
    final l = context.l10n;
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        content: Text(l.orgLeaveConfirm(org.name)),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.commonCancel)),
          TextButton(onPressed: () => Navigator.pop(c, true), child: Text(l.commonConfirm)),
        ],
      ),
    );
    if (ok != true) return;
    await ref.read(organizationRepositoryProvider).leave();
    ref.invalidate(organizationProvider);
  }

  Future<void> _pickDepartment(BuildContext context, WidgetRef ref) async {
    final picked = await showModalBottomSheet<String>(
      context: context,
      builder: (c) => SafeArea(
        child: ListView(shrinkWrap: true, children: [
          for (final d in org.departmentOptions) ListTile(title: Text(d), trailing: d == org.department ? const Icon(Icons.check) : null, onTap: () => Navigator.pop(c, d)),
        ]),
      ),
    );
    if (picked == null) return;
    try {
      await ref.read(organizationRepositoryProvider).setDepartment(picked);
      ref.invalidate(organizationProvider);
    } on ApiException catch (e) {
      if (context.mounted) showAppSnack(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    return RefreshIndicator(
      color: p.green,
      onRefresh: () async => ref.invalidate(organizationProvider),
      child: ListView(
        padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter, vertical: AppSpacing.sm),
        children: [
          AppCard(
            padding: const EdgeInsetsDirectional.all(AppSpacing.xl),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Icon(Icons.apartment_rounded, color: p.green),
                const SizedBox(width: AppSpacing.sm),
                Expanded(child: Text(org.name, style: context.text.titleLarge)),
              ]),
              const SizedBox(height: AppSpacing.xs),
              Text(l.orgMembers(Fa.number(org.members)), style: context.text.bodySmall),
              if (!org.active) Text(l.orgInactive, style: context.text.bodySmall?.copyWith(color: p.danger)),
              const Padding(padding: EdgeInsets.symmetric(vertical: AppSpacing.md), child: Divider()),
              Row(children: [
                Expanded(child: _Stat(label: l.orgMyRank, value: org.myRank == null ? '—' : Fa.number(org.myRank!))),
                Expanded(child: _Stat(label: l.orgMySteps, value: Fa.number(org.mySteps))),
              ]),
              if (org.departmentOptions.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.md),
                InkWell(
                  onTap: () => _pickDepartment(context, ref),
                  child: Row(children: [
                    Text('${l.orgDepartment}: ', style: context.text.bodyMedium),
                    Text(org.department ?? l.orgPickDepartment, style: context.text.bodyMedium?.copyWith(color: p.green, fontWeight: FontWeight.w600)),
                  ]),
                ),
              ],
            ]),
          ),
          if (org.challenges.isNotEmpty) ...[
            SectionHeader(title: l.orgChallenges),
            for (final c in org.challenges)
              Padding(
                padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.sm),
                child: AppCard(
                  onTap: () => context.push('/challenges/${c.id}'),
                  child: Row(children: [
                    Icon(Icons.emoji_events_outlined, color: p.goldInk),
                    const SizedBox(width: AppSpacing.sm),
                    Expanded(child: Text(c.title, style: context.text.titleSmall)),
                    Text(l.orgEndsIn(FaDate.relativeFuture(c.endsAt)), style: context.text.bodySmall),
                  ]),
                ),
              ),
          ],
          SectionHeader(title: l.orgWeekRanking),
          AppCard(
            padding: const EdgeInsetsDirectional.symmetric(vertical: AppSpacing.xs),
            child: Column(children: [
              for (final r in org.ranking)
                ListTile(
                  dense: true,
                  tileColor: r.isMe ? p.greenSoft : null,
                  leading: SizedBox(width: 28, child: Text(Fa.number(r.rank), textAlign: TextAlign.center, style: context.text.titleSmall)),
                  title: Text(r.isMe ? l.orgYou : r.name),
                  subtitle: r.department == null ? null : Text(r.department!),
                  trailing: Text(Fa.number(r.steps), style: context.text.titleSmall),
                ),
            ]),
          ),
          if (org.departments.length > 1) ...[
            SectionHeader(title: l.orgDepartments),
            AppCard(
              child: Column(children: [
                for (final d in org.departments)
                  Padding(
                    padding: const EdgeInsetsDirectional.symmetric(vertical: AppSpacing.xs),
                    child: Row(children: [
                      Expanded(child: Text('${d.name} (${Fa.number(d.members)})', style: context.text.bodyMedium)),
                      Text(d.avgSteps == null ? l.orgSmallGroup : l.orgAvg(Fa.number(d.avgSteps!)), style: context.text.bodySmall),
                    ]),
                  ),
              ]),
            ),
          ],
          const SizedBox(height: AppSpacing.lg),
          Center(child: AppButton.ghost(label: l.orgLeave, onPressed: () => _leave(context, ref))),
          const SizedBox(height: AppSpacing.xxl),
        ],
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(value, style: context.text.headlineSmall),
        Text(label, style: context.text.labelSmall),
      ]);
}
