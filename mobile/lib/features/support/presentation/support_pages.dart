import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/dates.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/state_views.dart';
import '../data/support_repository.dart';

class SupportPage extends ConsumerWidget {
  const SupportPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    return Scaffold(
      appBar: AppBar(title: Text(l.supportTitle)),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          await context.push('/support/new');
          ref.invalidate(ticketsProvider);
        },
        icon: const Icon(Icons.edit_outlined),
        label: Text(l.supportNew),
      ),
      body: RefreshIndicator(
        color: p.green,
        onRefresh: () async => ref.invalidate(ticketsProvider),
        child: AsyncView(
          value: ref.watch(ticketsProvider),
          onRetry: () => ref.invalidate(ticketsProvider),
          data: (tickets) => tickets.isEmpty
              ? ListView(children: [EmptyView(title: l.supportEmpty, actionLabel: l.supportFaqOpen, onAction: () => context.push('/faq'))])
              : ListView.separated(
                  padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, AppSpacing.gutter, AppSpacing.gutter, 96),
                  itemCount: tickets.length,
                  separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
                  itemBuilder: (_, i) {
                    final t = tickets[i];
                    return AppCard(
                      onTap: () async {
                        await context.push('/support/${t.id}');
                        ref.invalidate(ticketsProvider);
                      },
                      child: Row(children: [
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(t.subject, style: context.text.titleSmall, maxLines: 1, overflow: TextOverflow.ellipsis),
                            Text('${t.number} · ${t.categoryLabel} · ${FaDate.relative(t.lastMessageAt)}', style: context.text.bodySmall),
                          ]),
                        ),
                        const SizedBox(width: AppSpacing.sm),
                        Text(t.statusLabel, style: context.text.labelMedium?.copyWith(color: t.awaitingMe ? p.goldInk : (t.canReply ? p.info : p.inkSubtle))),
                      ]),
                    );
                  },
                ),
        ),
      ),
    );
  }
}

class NewTicketPage extends ConsumerStatefulWidget {
  const NewTicketPage({super.key});

  @override
  ConsumerState<NewTicketPage> createState() => _NewTicketPageState();
}

class _NewTicketPageState extends ConsumerState<NewTicketPage> {
  final _form = GlobalKey<FormState>();
  final _subject = TextEditingController();
  final _body = TextEditingController();
  String? _category;
  bool _busy = false;

  @override
  void dispose() {
    _subject.dispose();
    _body.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final t = await ref.read(supportRepositoryProvider).open(category: _category!, subject: _subject.text.trim(), body: _body.text.trim());
      if (!mounted) return;
      final router = GoRouter.maybeOf(context);
      Navigator.of(context).pop();
      router?.push('/support/${t.id}');
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    return Scaffold(
      appBar: AppBar(title: Text(l.supportNew)),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
          children: [
            AppCard(
              color: p.greenSoft,
              borderColor: p.greenSoft,
              onTap: () => context.push('/faq'),
              child: Row(children: [
                Icon(Icons.help_outline_rounded, color: p.green),
                const SizedBox(width: AppSpacing.sm),
                Expanded(child: Text(l.supportFaqHint, style: context.text.bodyMedium)),
                Text(l.supportFaqOpen, style: context.text.labelLarge?.copyWith(color: p.green)),
              ]),
            ),
            const SizedBox(height: AppSpacing.lg),
            ref.watch(supportCategoriesProvider).when(
                  data: (cats) => DropdownButtonFormField<String>(
                    initialValue: _category,
                    decoration: InputDecoration(labelText: l.supportCategory),
                    items: [for (final c in cats) DropdownMenuItem(value: c.id, child: Text(c.label))],
                    onChanged: (v) => setState(() => _category = v),
                    validator: (v) => v == null ? l.fieldRequired : null,
                  ),
                  loading: () => const LinearProgressIndicator(),
                  error: (e, _) => ErrorView(error: e, compact: true, onRetry: () => ref.invalidate(supportCategoriesProvider)),
                ),
            const SizedBox(height: AppSpacing.md),
            TextFormField(
              controller: _subject,
              maxLength: 150,
              decoration: InputDecoration(labelText: l.supportSubject),
              validator: (v) => (v ?? '').trim().length < 4 ? l.fieldRequired : null,
            ),
            TextFormField(
              controller: _body,
              maxLines: 6,
              maxLength: 3000,
              decoration: InputDecoration(labelText: l.supportBody, helperText: l.supportBodyHint, helperMaxLines: 2, alignLabelWithHint: true),
              validator: (v) => (v ?? '').trim().length < 10 ? l.supportTooShort : null,
            ),
            const SizedBox(height: AppSpacing.lg),
            AppButton(label: l.supportSend, icon: Icons.send_rounded, loading: _busy, onPressed: _send),
          ],
        ),
      ),
    );
  }
}

class TicketPage extends ConsumerStatefulWidget {
  const TicketPage({super.key, required this.id});

  final String id;

  @override
  ConsumerState<TicketPage> createState() => _TicketPageState();
}

class _TicketPageState extends ConsumerState<TicketPage> {
  final _reply = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _reply.dispose();
    super.dispose();
  }

  Future<void> _run(Future<void> Function() action) async {
    setState(() => _busy = true);
    try {
      await action();
      ref.invalidate(ticketProvider(widget.id));
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final ticket = ref.watch(ticketProvider(widget.id));
    return Scaffold(
      appBar: AppBar(
        title: Text(ticket.value?.number ?? l.supportTitle),
        actions: [
          if (ticket.value?.canReply ?? false)
            TextButton(onPressed: _busy ? null : () => _run(() => ref.read(supportRepositoryProvider).close(widget.id)), child: Text(l.supportClose)),
        ],
      ),
      body: AsyncView(
        value: ticket,
        onRetry: () => ref.invalidate(ticketProvider(widget.id)),
        data: (t) => Column(children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
              children: [
                Text(t.subject, style: context.text.titleMedium),
                Text('${t.categoryLabel} · ${t.statusLabel}', style: context.text.bodySmall),
                const SizedBox(height: AppSpacing.lg),
                for (final m in t.messages)
                  Align(
                    alignment: m.fromMe ? AlignmentDirectional.centerStart : AlignmentDirectional.centerEnd,
                    child: Container(
                      constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.78),
                      margin: const EdgeInsetsDirectional.only(bottom: AppSpacing.sm),
                      padding: const EdgeInsetsDirectional.all(AppSpacing.md),
                      decoration: BoxDecoration(
                        color: m.fromMe ? p.greenSoft : p.surfaceSunken,
                        borderRadius: BorderRadius.circular(AppRadius.md),
                      ),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(m.fromMe ? l.supportMe : l.supportAgent, style: context.text.labelSmall?.copyWith(color: m.fromMe ? p.greenStrong : p.info)),
                        const SizedBox(height: AppSpacing.xxs),
                        SelectableText(m.body, style: context.text.bodyMedium),
                        const SizedBox(height: AppSpacing.xxs),
                        Text(FaDate.relative(m.at), style: context.text.labelSmall),
                      ]),
                    ),
                  ),
              ],
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, AppSpacing.xs, AppSpacing.sm, AppSpacing.sm),
              child: t.canReply
                  ? Row(children: [
                      Expanded(
                        child: TextField(controller: _reply, minLines: 1, maxLines: 4, maxLength: 3000, decoration: InputDecoration(hintText: l.supportReplyHint, counterText: '')),
                      ),
                      IconButton(
                        tooltip: l.supportSend,
                        icon: Icon(Icons.send_rounded, color: p.green, textDirection: TextDirection.ltr),
                        onPressed: _busy
                            ? null
                            : () {
                                final text = _reply.text.trim();
                                if (text.length < 2) return;
                                _run(() async {
                                  await ref.read(supportRepositoryProvider).reply(widget.id, text);
                                  _reply.clear();
                                });
                              },
                      ),
                    ])
                  : Text(l.supportClosed, textAlign: TextAlign.center, style: context.text.bodySmall),
            ),
          ),
        ]),
      ),
    );
  }
}
