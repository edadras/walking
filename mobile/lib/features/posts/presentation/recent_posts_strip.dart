import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/net_image.dart';
import '../application/feed_controller.dart';

/// Home: a row of the latest walk photos; hidden until there are some.
class RecentPostsStrip extends ConsumerWidget {
  const RecentPostsStrip({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final posts = ref.watch(feedProvider(false)).value?.posts ?? const [];
    if (posts.isEmpty) return const SizedBox.shrink();
    final l = context.l10n;
    final p = context.palette;
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      SectionHeader(title: l.homePosts, actionLabel: l.commonSeeAll, onAction: () => context.push('/posts')),
      SizedBox(
        height: 132,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          itemCount: posts.length.clamp(0, 10),
          separatorBuilder: (_, _) => const SizedBox(width: AppSpacing.sm),
          itemBuilder: (context, i) {
            final post = posts[i];
            return GestureDetector(
              onTap: () => context.push('/posts'),
              child: ClipRRect(
                borderRadius: AppRadius.mdAll,
                child: SizedBox(
                  width: 112,
                  child: Stack(fit: StackFit.expand, children: [
                    NetImage(post.thumbUrl, fit: BoxFit.cover),
                    PositionedDirectional(
                      start: 0,
                      end: 0,
                      bottom: 0,
                      child: Container(
                        padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.sm, vertical: AppSpacing.xs),
                        decoration: const BoxDecoration(gradient: LinearGradient(begin: Alignment.bottomCenter, end: Alignment.topCenter, colors: [Color(0xAA000000), Color(0x00000000)])),
                        child: Row(children: [
                          const Icon(Icons.favorite_rounded, size: 12, color: Colors.white),
                          const SizedBox(width: 2),
                          Text(Fa.number(post.likesCount), style: context.text.labelSmall?.copyWith(color: Colors.white)),
                          const SizedBox(width: AppSpacing.sm),
                          const Icon(Icons.visibility_rounded, size: 12, color: Colors.white),
                          const SizedBox(width: 2),
                          Text(Fa.number(post.viewsCount), style: context.text.labelSmall?.copyWith(color: Colors.white)),
                        ]),
                      ),
                    ),
                    if (post.walkType == 'bicycle')
                      PositionedDirectional(top: 6, end: 6, child: CircleAvatar(radius: 11, backgroundColor: p.surface, child: Icon(Icons.pedal_bike_rounded, size: 14, color: p.info))),
                  ]),
                ),
              ),
            );
          },
        ),
      ),
    ]);
  }
}
