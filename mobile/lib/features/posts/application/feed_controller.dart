import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/posts.dart';

@immutable
class FeedState {
  const FeedState({this.posts = const [], this.next, this.loadingMore = false});

  final List<WalkPost> posts;
  final String? next;
  final bool loadingMore;

  bool get done => next == null;

  FeedState copyWith({List<WalkPost>? posts, String? next, bool? loadingMore, bool clearNext = false}) =>
      FeedState(posts: posts ?? this.posts, next: clearNext ? null : (next ?? this.next), loadingMore: loadingMore ?? this.loadingMore);
}

/// Everyone's photos (`mine: false`) or my own, newest first, paged.
class FeedController extends AsyncNotifier<FeedState> {
  FeedController(this.mine);

  final bool mine;

  PostsRepository get _repo => ref.read(postsRepositoryProvider);

  @override
  Future<FeedState> build() async {
    final page = await ref.watch(postsRepositoryProvider).feed(mine: mine);
    return FeedState(posts: page.posts, next: page.next);
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || current.done || current.loadingMore) return;
    state = AsyncData(current.copyWith(loadingMore: true));
    try {
      final page = await _repo.feed(mine: mine, before: current.next);
      state = AsyncData(FeedState(posts: [...current.posts, ...page.posts], next: page.next));
    } catch (_) {
      state = AsyncData(current.copyWith(loadingMore: false));
    }
  }

  /// Optimistic; the server's count wins, a failure rolls back.
  Future<void> toggleLike(WalkPost post) async {
    _replace(post.copyWith(liked: !post.liked, likesCount: post.likesCount + (post.liked ? -1 : 1)));
    try {
      final count = post.liked ? await _repo.unlike(post.id) : await _repo.like(post.id);
      _replace(post.copyWith(liked: !post.liked, likesCount: count));
    } catch (_) {
      _replace(post);
    }
  }

  Future<void> report(WalkPost post, String reason) async {
    await _repo.report(post.id, reason);
    _remove(post.id); // hidden for the reporter right away
  }

  Future<void> delete(WalkPost post) async {
    await _repo.delete(post.id);
    _remove(post.id);
  }

  void _replace(WalkPost post) {
    final current = state.value;
    if (current == null) return;
    state = AsyncData(current.copyWith(posts: [for (final p in current.posts) p.id == post.id ? post : p]));
  }

  void _remove(String id) {
    final current = state.value;
    if (current == null) return;
    state = AsyncData(current.copyWith(posts: current.posts.where((p) => p.id != id).toList()));
  }
}

final feedProvider = AsyncNotifierProvider.autoDispose.family<FeedController, FeedState, bool>(FeedController.new);
