import 'dart:convert';
import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

import '../../../core/network/api_exception.dart';
import '../data/posts.dart';

class OutboxPhoto {
  const OutboxPhoto({required this.path, required this.capturedAt, this.caption});

  factory OutboxPhoto.fromJson(Map<String, dynamic> j) =>
      OutboxPhoto(path: j['path'] as String, capturedAt: DateTime.parse(j['captured_at'] as String), caption: j['caption'] as String?);

  final String path;
  final DateTime capturedAt;
  final String? caption;

  Map<String, dynamic> toJson() => {'path': path, 'captured_at': capturedAt.toUtc().toIso8601String(), 'caption': caption};
}

/// Photos taken on a walk wait here (a private app folder, not the gallery) until they
/// reach the server, so a walk without signal loses nothing. Uploaded files are deleted.
class PhotoOutbox extends Notifier<List<OutboxPhoto>> {
  Directory? _dir;
  bool _flushing = false;

  @override
  List<OutboxPhoto> build() {
    _load();
    return const [];
  }

  Future<Directory> _folder() async => _dir ??= await ref.read(outboxDirProvider)();

  Future<File> get _index async => File(p.join((await _folder()).path, 'outbox.json'));

  Future<void> _load() async {
    try {
      final f = await _index;
      if (await f.exists()) {
        state = (jsonDecode(await f.readAsString()) as List).map((e) => OutboxPhoto.fromJson(e as Map<String, dynamic>)).toList();
      }
    } catch (_) {
      state = const [];
    }
  }

  Future<void> _save() async => (await _index).writeAsString(jsonEncode(state.map((e) => e.toJson()).toList()));

  /// Keeps a private copy of the camera file and tries to upload right away.
  Future<void> add(String cameraPath, DateTime capturedAt, String? caption) async {
    final dir = await _folder();
    final copy = await File(cameraPath).copy(p.join(dir.path, 'walk-${capturedAt.millisecondsSinceEpoch}.jpg'));
    try {
      await File(cameraPath).delete();
    } catch (_) {}
    state = [...state, OutboxPhoto(path: copy.path, capturedAt: capturedAt, caption: caption)];
    await _save();
    await flush();
  }

  /// Uploads what it can; network trouble keeps the rest for next time.
  Future<int> flush() async {
    if (_flushing || state.isEmpty) return 0;
    _flushing = true;
    var sent = 0;
    try {
      for (final photo in [...state]) {
        try {
          await ref.read(postsRepositoryProvider).upload(photo.path, photo.capturedAt, photo.caption);
          sent++;
          await _drop(photo);
        } on ApiException catch (e) {
          if (e.isNetwork || e.status == null || e.status! >= 500 || e.status == 429) break;
          await _drop(photo); // refused for good (limit, invalid image): don't retry forever
        }
      }
    } finally {
      _flushing = false;
    }
    return sent;
  }

  Future<void> _drop(OutboxPhoto photo) async {
    state = state.where((e) => e.path != photo.path).toList();
    await _save();
    try {
      await File(photo.path).delete();
    } catch (_) {}
  }
}

final outboxDirProvider = Provider<Future<Directory> Function()>((ref) => () async {
      final base = await getApplicationSupportDirectory();
      return Directory(p.join(base.path, 'walk_photos')).create(recursive: true);
    });

final photoOutboxProvider = NotifierProvider<PhotoOutbox, List<OutboxPhoto>>(PhotoOutbox.new);
