import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/posts.dart';

/// Collects posts that were really on screen and reports them in small batches.
/// Each post is reported once per app session; the server counts each person once ever.
class ViewTracker {
  ViewTracker(this._repo);

  final PostsRepository _repo;
  final _sent = <String>{};
  final _queue = <String>{};
  Timer? _timer;

  void seen(String id) {
    if (_sent.contains(id) || !_queue.add(id)) return;
    _timer ??= Timer(const Duration(seconds: 4), flush);
  }

  Future<void> flush() async {
    _timer?.cancel();
    _timer = null;
    if (_queue.isEmpty) return;
    final batch = _queue.take(50).toList();
    _queue.removeAll(batch);
    try {
      await _repo.views(batch);
      _sent.addAll(batch);
    } catch (_) {
      _queue.addAll(batch); // retried with the next batch
    }
  }

  void dispose() => _timer?.cancel();
}

final viewTrackerProvider = Provider<ViewTracker>((ref) {
  final t = ViewTracker(ref.watch(postsRepositoryProvider));
  ref.onDispose(() {
    t.flush();
    t.dispose();
  });
  return t;
});
