import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:share_plus/share_plus.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/app_text_field.dart';
import '../../../core/widgets/net_image.dart';
import '../../../core/widgets/state_views.dart';
import '../data/social.dart';

/// Friends: this week's ranking, requests, and friendly races.
class FriendsPage extends ConsumerStatefulWidget {
  const FriendsPage({super.key});

  @override
  ConsumerState<FriendsPage> createState() => _FriendsPageState();
}

class _FriendsPageState extends ConsumerState<FriendsPage> {
  final _code = TextEditingController();
  bool _adding = false;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _run(Future<void> Function() action, {String? done}) async {
    try {
      await action();
      ref.invalidate(friendsProvider);
      if (done != null && mounted) showAppSnack(context, done);
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    }
  }

  Future<void> _add() async {
    if (_code.text.trim().isEmpty) return;
    setState(() => _adding = true);
    await _run(() => ref.read(socialRepositoryProvider).addFriend(_code.text), done: context.l10n.friendRequested);
    if (mounted) {
      _code.clear();
      setState(() => _adding = false);
    }
  }

  Future<void> _unfriend(FriendCard f) async {
    final l = context.l10n;
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        content: Text(l.friendRemoveConfirm(f.name)),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.commonCancel)),
          TextButton(onPressed: () => Navigator.pop(c, true), child: Text(l.commonConfirm)),
        ],
      ),
    );
    if (ok == true) await _run(() => ref.read(socialRepositoryProvider).remove(f.friendshipId!));
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    return Scaffold(
      appBar: AppBar(title: Text(l.friendsTitle)),
      floatingActionButton: ref.watch(friendsProvider).value?.friends.isNotEmpty == true
          ? FloatingActionButton.extended(
              onPressed: () => showNewRaceSheet(context, ref.read(friendsProvider).value!.friends),
              icon: const Icon(Icons.emoji_events_outlined),
              label: Text(l.raceNew),
            )
          : null,
      body: RefreshIndicator(
        color: p.green,
        onRefresh: () async {
          ref.invalidate(friendsProvider);
          ref.invalidate(friendRacesProvider);
        },
        child: AsyncView(
          value: ref.watch(friendsProvider),
          onRetry: () => ref.invalidate(friendsProvider),
          data: (o) => ListView(
            padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, AppSpacing.gutter, AppSpacing.gutter, 96),
            children: [
              AppCard(
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Text(l.friendMyCode, style: context.text.labelMedium),
                  Row(children: [
                    Expanded(child: SelectableText(o.code, style: context.text.headlineSmall?.copyWith(letterSpacing: 2))),
                    IconButton(
                      tooltip: l.orderCodeCopied,
                      icon: const Icon(Icons.copy_rounded),
                      onPressed: () async {
                        await Clipboard.setData(ClipboardData(text: o.code));
                        if (context.mounted) showAppSnack(context, l.orderCodeCopied);
                      },
                    ),
                    IconButton(
                      tooltip: l.friendShare,
                      icon: const Icon(Icons.share_outlined),
                      onPressed: () => SharePlus.instance.share(ShareParams(text: l.friendShareText(o.code))),
                    ),
                  ]),
                  const SizedBox(height: AppSpacing.md),
                  Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Expanded(child: AppTextField(label: l.friendAddLabel, hint: l.friendAddHint, controller: _code, maxLength: 12)),
                    const SizedBox(width: AppSpacing.sm),
                    Padding(
                      padding: const EdgeInsetsDirectional.only(top: 22),
                      child: AppButton(label: l.friendAdd, expand: false, loading: _adding, onPressed: _add),
                    ),
                  ]),
                ]),
              ),
              if (o.incoming.isNotEmpty) ...[
                _Section(l.friendIncoming),
                for (final f in o.incoming)
                  _PersonTile(
                    person: f,
                    trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                      IconButton(
                        tooltip: l.friendAccept,
                        icon: Icon(Icons.check_circle_rounded, color: p.green),
                        onPressed: () => _run(() => ref.read(socialRepositoryProvider).accept(f.friendshipId!), done: l.friendAccepted),
                      ),
                      IconButton(
                        tooltip: l.friendDecline,
                        icon: Icon(Icons.cancel_outlined, color: p.inkSubtle),
                        onPressed: () => _run(() => ref.read(socialRepositoryProvider).remove(f.friendshipId!)),
                      ),
                    ]),
                  ),
              ],
              if (o.outgoing.isNotEmpty) ...[
                _Section(l.friendOutgoing),
                for (final f in o.outgoing)
                  _PersonTile(
                    person: f,
                    subtitle: l.friendWaiting,
                    trailing: TextButton(onPressed: () => _run(() => ref.read(socialRepositoryProvider).remove(f.friendshipId!)), child: Text(l.commonCancel)),
                  ),
              ],
              const _Races(),
              _Section(l.friendWeekRanking),
              if (o.friends.isEmpty)
                Padding(padding: const EdgeInsets.all(AppSpacing.lg), child: Text(l.friendEmpty, textAlign: TextAlign.center, style: context.text.bodyMedium))
              else
                for (final (i, f) in o.ranking.indexed)
                  _PersonTile(
                    person: f,
                    rank: i + 1,
                    subtitle: l.friendWeekSteps(Fa.number(f.weekSteps)),
                    highlight: f.isMe,
                    onLongPress: f.isMe ? null : () => _unfriend(f),
                  ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section(this.title);

  final String title;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsetsDirectional.only(top: AppSpacing.xl, bottom: AppSpacing.sm),
        child: Text(title, style: context.text.titleMedium),
      );
}

class _PersonTile extends StatelessWidget {
  const _PersonTile({required this.person, this.subtitle, this.trailing, this.rank, this.highlight = false, this.onLongPress});

  final FriendCard person;
  final String? subtitle;
  final Widget? trailing;
  final int? rank;
  final bool highlight;
  final VoidCallback? onLongPress;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    return Padding(
      padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.xs),
      child: Material(
        color: highlight ? p.greenSoft : p.surface,
        borderRadius: AppRadius.mdAll,
        child: ListTile(
          shape: RoundedRectangleBorder(borderRadius: AppRadius.mdAll),
          onLongPress: onLongPress,
          leading: Row(mainAxisSize: MainAxisSize.min, children: [
            if (rank != null) SizedBox(width: 24, child: Text(Fa.digits(rank!), style: context.text.titleSmall, textAlign: TextAlign.center)),
            NetAvatar(
              url: person.avatarUrl,
              radius: 18,
              backgroundColor: p.surfaceSunken,
              child: Text(person.name.isEmpty ? '؟' : person.name.characters.first),
            ),
          ]),
          title: Text(person.isMe ? '${person.name} (${l.lbYou})' : person.name, maxLines: 1, overflow: TextOverflow.ellipsis),
          subtitle: Text(subtitle ?? l.profileLevel(Fa.digits(person.level))),
          trailing: trailing,
        ),
      ),
    );
  }
}

class _Races extends ConsumerWidget {
  const _Races();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    final races = ref.watch(friendRacesProvider).value ?? const [];
    if (races.isEmpty) return const SizedBox.shrink();
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      _Section(l.raceTitle),
      for (final r in races)
        Padding(
          padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.xs),
          child: AppCard(
            borderColor: r.myStatus == 'invited' ? p.gold : null,
            onTap: () => context.push('/friend-challenges/${r.id}'),
            child: Row(children: [
              Icon(Icons.emoji_events_outlined, color: r.status == 'running' ? p.goldInk : p.inkMuted),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(r.title, style: context.text.titleSmall),
                  Text('${FaDate.dayMonth(r.startsOn)} – ${FaDate.dayMonth(r.endsOn)} · ${l.raceMembers(Fa.digits(r.members))}', style: context.text.bodySmall),
                ]),
              ),
              Text(
                r.myStatus == 'invited' ? l.raceInvited : raceStatusLabel(l, r.status),
                style: context.text.labelMedium?.copyWith(color: r.myStatus == 'invited' ? p.goldInk : null),
              ),
            ]),
          ),
        ),
    ]);
  }
}

String raceStatusLabel(AppLocalizations l, String status) => switch (status) {
      'upcoming' => l.raceUpcoming,
      'running' => l.raceRunning,
      _ => l.raceFinished,
    };

Future<void> showNewRaceSheet(BuildContext context, List<FriendCard> friends) => showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (_) => NewRaceSheet(friends: friends),
    );

class NewRaceSheet extends ConsumerStatefulWidget {
  const NewRaceSheet({super.key, required this.friends});

  final List<FriendCard> friends;

  @override
  ConsumerState<NewRaceSheet> createState() => _NewRaceSheetState();
}

class _NewRaceSheetState extends ConsumerState<NewRaceSheet> {
  final _title = TextEditingController();
  final _picked = <String>{};
  int _days = 7;
  bool _busy = false;

  @override
  void dispose() {
    _title.dispose();
    super.dispose();
  }

  Future<void> _create() async {
    setState(() => _busy = true);
    try {
      final race = await ref.read(socialRepositoryProvider).createRace(title: _title.text.trim(), days: _days, friendIds: _picked.toList());
      ref.invalidate(friendRacesProvider);
      if (!mounted) return;
      final router = GoRouter.maybeOf(context);
      Navigator.pop(context);
      router?.push('/friend-challenges/${race.id}');
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Padding(
      padding: EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, 0, AppSpacing.gutter, AppSpacing.xl + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text(l.raceNew, style: context.text.titleLarge),
        const SizedBox(height: AppSpacing.xs),
        Text(l.raceHint, style: context.text.bodySmall),
        const SizedBox(height: AppSpacing.md),
        AppTextField(label: l.raceName, hint: l.raceNameHint, controller: _title, maxLength: 60),
        const SizedBox(height: AppSpacing.sm),
        SegmentedButton<int>(
          showSelectedIcon: false,
          segments: [for (final d in const [3, 7, 14]) ButtonSegment(value: d, label: Text(l.raceDays(Fa.digits(d))))],
          selected: {_days},
          onSelectionChanged: (v) => setState(() => _days = v.first),
        ),
        const SizedBox(height: AppSpacing.sm),
        ConstrainedBox(
          constraints: const BoxConstraints(maxHeight: 260),
          child: ListView(shrinkWrap: true, children: [
            for (final f in widget.friends)
              CheckboxListTile(
                value: _picked.contains(f.userId),
                title: Text(f.name),
                onChanged: _picked.length >= 9 && !_picked.contains(f.userId)
                    ? null
                    : (v) => setState(() => v == true ? _picked.add(f.userId) : _picked.remove(f.userId)),
              ),
          ]),
        ),
        const SizedBox(height: AppSpacing.md),
        AppButton(
          label: l.raceCreate,
          loading: _busy,
          onPressed: _picked.isEmpty || _title.text.trim().length < 3 ? null : _create,
        ),
      ]),
    );
  }

  @override
  void initState() {
    super.initState();
    _title.addListener(() => setState(() {}));
  }
}

/// Standings of one friendly race.
class FriendRacePage extends ConsumerWidget {
  const FriendRacePage({super.key, required this.id});

  final String id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    Future<void> act(Future<void> Function() f) async {
      try {
        await f();
        ref.invalidate(friendRaceProvider(id));
        ref.invalidate(friendRacesProvider);
      } on ApiException catch (e) {
        if (context.mounted) showAppSnack(context, e.message);
      }
    }

    return Scaffold(
      appBar: AppBar(title: Text(l.raceTitle)),
      body: AsyncView(
        value: ref.watch(friendRaceProvider(id)),
        onRetry: () => ref.invalidate(friendRaceProvider(id)),
        data: (r) => ListView(padding: const EdgeInsetsDirectional.all(AppSpacing.gutter), children: [
          Text(r.title, style: context.text.headlineSmall),
          const SizedBox(height: AppSpacing.xs),
          Text('${FaDate.long(r.startsOn)} – ${FaDate.long(r.endsOn)} · ${raceStatusLabel(l, r.status)}', style: context.text.bodySmall),
          Text(l.raceBy(r.creator), style: context.text.bodySmall),
          if (r.myStatus == 'invited') ...[
            const SizedBox(height: AppSpacing.lg),
            AppCard(
              borderColor: p.gold,
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Text(l.raceInviteBody, style: context.text.bodyMedium),
                const SizedBox(height: AppSpacing.md),
                Row(children: [
                  Expanded(child: AppButton(label: l.raceJoin, onPressed: () => act(() => ref.read(socialRepositoryProvider).join(id)))),
                  const SizedBox(width: AppSpacing.sm),
                  AppButton.ghost(label: l.friendDecline, onPressed: () async {
                    await act(() => ref.read(socialRepositoryProvider).leave(id));
                    if (context.mounted) Navigator.of(context).maybePop();
                  }),
                ]),
              ]),
            ),
          ],
          const SizedBox(height: AppSpacing.lg),
          if (r.status == 'upcoming') Text(l.raceStartsTomorrow, style: context.text.bodyMedium),
          for (final (i, m) in r.standings.indexed)
            _PersonTile(person: m, rank: i + 1, highlight: m.isMe, subtitle: l.raceSteps(Fa.number(m.weekSteps)),
                trailing: i == 0 && r.status != 'upcoming' && m.weekSteps > 0 ? Icon(Icons.emoji_events_rounded, color: p.goldInk) : null),
          if (r.invited.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.md),
            Text(l.racePending(r.invited.join('، ')), style: context.text.bodySmall),
          ],
          const SizedBox(height: AppSpacing.lg),
          Text(l.raceFairPlay, style: context.text.labelSmall),
          if (r.myStatus == 'joined' && r.status != 'finished') ...[
            const SizedBox(height: AppSpacing.lg),
            AppButton.ghost(label: l.raceLeave, onPressed: () async {
              await act(() => ref.read(socialRepositoryProvider).leave(id));
              if (context.mounted) Navigator.of(context).maybePop();
            }),
          ],
        ]),
      ),
    );
  }
}
