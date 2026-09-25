import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart' show ScrollCacheExtent;
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/skeleton.dart';
import '../../../core/widgets/state_views.dart';
import '../../config/data/app_config.dart';
import '../application/feed_controller.dart';
import '../application/photo_outbox.dart';
import 'post_card.dart';

/// Everyone's walk photos and my own (with their review state and what they earned).
class FeedPage extends StatelessWidget {
  const FeedPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: Text(l.postsTitle),
          bottom: TabBar(tabs: [Tab(text: l.postsAll), Tab(text: l.postsMine)]),
        ),
        body: const TabBarView(children: [_Feed(mine: false), _Feed(mine: true)]),
      ),
    );
  }
}

class _Feed extends ConsumerWidget {
  const _Feed({required this.mine});

  final bool mine;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final p = context.palette;
    final waiting = mine ? ref.watch(photoOutboxProvider).length : 0;
    return RefreshIndicator(
      onRefresh: () async {
        if (mine) await ref.read(photoOutboxProvider.notifier).flush();
        ref.invalidate(feedProvider(mine));
        await ref.read(feedProvider(mine).future);
      },
      child: AsyncView(
        value: ref.watch(feedProvider(mine)),
        onRetry: () => ref.invalidate(feedProvider(mine)),
        skeleton: const SingleChildScrollView(
          physics: NeverScrollableScrollPhysics(),
          padding: EdgeInsetsDirectional.all(AppSpacing.gutter),
          child: Shimmer(child: Column(children: [SkeletonBox(height: 320, radius: AppRadius.md), SizedBox(height: AppSpacing.lg), SkeletonBox(height: 320, radius: AppRadius.md)])),
        ),
        data: (feed) {
          final config = ref.watch(configProvider);
          final rule = l.postsRule(
            Fa.number((config.settings['social.points_per_like'] as num?) ?? 1),
            Fa.number((config.settings['social.views_per_point'] as num?) ?? 20),
            Fa.number((config.settings['social.max_points_per_post'] as num?) ?? 30),
          );
          return NotificationListener<ScrollNotification>(
            onNotification: (n) {
              if (n.metrics.extentAfter < 600) ref.read(feedProvider(mine).notifier).loadMore();
              return false;
            },
            child: ListView.builder(
              // No cache extent: a card only exists while it is on screen, which is what makes a "view".
              scrollCacheExtent: const ScrollCacheExtent.pixels(0),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
              itemCount: feed.posts.length + 2,
              itemBuilder: (context, i) {
                if (i == 0) {
                  return Padding(
                    padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.md),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      if (waiting > 0)
                        Padding(
                          padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.xs),
                          child: Row(children: [
                            Icon(Icons.cloud_upload_outlined, size: 16, color: p.goldInk),
                            const SizedBox(width: AppSpacing.xs),
                            Text(l.walkPhotoWaiting(Fa.number(waiting)), style: context.text.labelMedium?.copyWith(color: p.goldInk)),
                          ]),
                        ),
                      Text(rule, style: context.text.labelSmall),
                    ]),
                  );
                }
                if (i == feed.posts.length + 1) {
                  if (feed.posts.isEmpty) {
                    return EmptyView(title: mine ? l.postsMineEmpty : l.postsEmpty, message: l.postsEmptyHint, compact: true);
                  }
                  return feed.loadingMore ? const Padding(padding: EdgeInsets.all(AppSpacing.lg), child: LoadingView()) : const SizedBox(height: AppSpacing.x3);
                }
                final post = feed.posts[i - 1];
                return PostCard(key: ValueKey(post.id), post: post, mineFeed: mine);
              },
            ),
          );
        },
      ),
    );
  }
}
