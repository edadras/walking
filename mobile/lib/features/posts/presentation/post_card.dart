import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/dates.dart';
import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/net_image.dart';
import '../application/feed_controller.dart';
import '../application/view_tracker.dart';
import '../data/posts.dart';

/// One walk photo: author, picture, caption, the walk it came from, likes and real views.
class PostCard extends ConsumerStatefulWidget {
  const PostCard({super.key, required this.post, required this.mineFeed});

  final WalkPost post;
  final bool mineFeed;

  @override
  ConsumerState<PostCard> createState() => _PostCardState();
}

class _PostCardState extends ConsumerState<PostCard> {
  Timer? _seen;

  @override
  void initState() {
    super.initState();
    // Built only when on screen (the feed has no cache extent): a second on screen is a view.
    if (!widget.post.mine) {
      _seen = Timer(const Duration(milliseconds: 1200), () => ref.read(viewTrackerProvider).seen(widget.post.id));
    }
  }

  @override
  void dispose() {
    _seen?.cancel();
    super.dispose();
  }

  FeedController get _feed => ref.read(feedProvider(widget.mineFeed).notifier);

  Future<void> _menu() async {
    final l = context.l10n;
    final post = widget.post;
    if (post.mine) {
      final ok = await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          content: Text(l.postDeleteConfirm),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.commonCancel)),
            TextButton(onPressed: () => Navigator.pop(c, true), child: Text(l.commonDelete, style: TextStyle(color: c.palette.danger))),
          ],
        ),
      );
      if (ok == true) await _feed.delete(post);
      return;
    }
    final reason = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (c) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Padding(padding: const EdgeInsetsDirectional.all(AppSpacing.md), child: Text(l.postReportTitle, style: c.text.titleMedium)),
          for (final (key, label) in [
            ('inappropriate', l.postReasonInappropriate),
            ('privacy', l.postReasonPrivacy),
            ('spam', l.postReasonSpam),
            ('not_walk', l.postReasonNotWalk),
            ('other', l.postReasonOther),
          ])
            ListTile(title: Text(label), onTap: () => Navigator.pop(c, key)),
        ]),
      ),
    );
    if (reason == null) return;
    await _feed.report(post, reason);
    if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l.postReported)));
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final post = widget.post;
    final walk = switch (post.walkType) {
      'bicycle' when post.walkDistanceM != null => l.postWalkBike(Fa.decimal(post.walkDistanceM! / 1000)),
      _ when post.walkSteps != null && post.walkSteps! > 0 => l.postWalkSteps(Fa.number(post.walkSteps!)),
      _ => null,
    };

    return Container(
      margin: const EdgeInsetsDirectional.only(bottom: AppSpacing.lg),
      decoration: BoxDecoration(color: p.surface, borderRadius: AppRadius.mdAll, border: Border.all(color: p.border)),
      clipBehavior: Clip.antiAlias,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Padding(
          padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.md, AppSpacing.sm, AppSpacing.xs, AppSpacing.sm),
          child: Row(children: [
            CircleAvatar(
              radius: 18,
              backgroundColor: p.greenSoft,
              child: ClipOval(
                child: NetImage(post.author.avatarUrl, width: 36, height: 36, fallback: Icon(Icons.person_rounded, color: p.green, size: 20)),
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(post.author.name, style: context.text.titleSmall),
                Text([FaDate.relative(post.capturedAt), ?walk].join(' · '), style: context.text.labelSmall),
              ]),
            ),
            IconButton(
              tooltip: post.mine ? l.postDelete : l.postReport,
              icon: Icon(post.mine ? Icons.delete_outline_rounded : Icons.more_vert_rounded, color: p.inkMuted),
              onPressed: _menu,
            ),
          ]),
        ),
        AspectRatio(
          aspectRatio: post.aspect.clamp(0.6, 1.8),
          child: Semantics(image: true, label: post.caption ?? post.author.name, child: NetImage(post.imageUrl, fit: BoxFit.cover)),
        ),
        Padding(
          padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.xs, AppSpacing.xs, AppSpacing.md, AppSpacing.xs),
          child: Row(children: [
            IconButton(
              tooltip: Fa.number(post.likesCount),
              onPressed: post.mine || post.status == 'pending' ? null : () => _feed.toggleLike(post),
              icon: AnimatedSwitcher(
                duration: AppMotion.fast,
                transitionBuilder: (c, a) => ScaleTransition(scale: a, child: c),
                child: Icon(post.liked ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                    key: ValueKey(post.liked), color: post.liked ? const Color(0xFFE5484D) : p.inkMuted),
              ),
            ),
            Text(Fa.number(post.likesCount), style: context.text.labelLarge),
            const SizedBox(width: AppSpacing.lg),
            Icon(Icons.visibility_outlined, size: 20, color: p.inkMuted),
            const SizedBox(width: AppSpacing.xs),
            Text(Fa.number(post.viewsCount), style: context.text.labelLarge),
            const Spacer(),
            if (post.mine && post.status != null && post.status != 'published')
              _Badge(text: post.status == 'pending' ? l.postPending : l.postHidden, color: post.status == 'pending' ? p.goldInk : p.danger)
            else if (post.mine && (post.pointsAwarded ?? 0) > 0)
              _Badge(text: l.postPoints(Fa.number(post.pointsAwarded!)), color: p.greenStrong),
          ]),
        ),
        if (post.caption != null && post.caption!.isNotEmpty)
          Padding(
            padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.md, 0, AppSpacing.md, AppSpacing.md),
            child: Text(post.caption!, style: context.text.bodyMedium),
          ),
      ]),
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge({required this.text, required this.color});

  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.sm, vertical: 2),
        decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: const BorderRadius.all(Radius.circular(AppRadius.xs))),
        child: Text(text, style: context.text.labelSmall?.copyWith(color: color, fontWeight: FontWeight.w600)),
      );
}
