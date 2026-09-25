import 'dart:convert';
import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path/path.dart' as p;

import '../../../core/network/api_exception.dart';
import '../../posts/application/photo_outbox.dart';
import '../data/route_map.dart';

/// The lines of finished walks waiting to be sent (only kept when the user shares routes).
class RouteOutbox {
  RouteOutbox(this._dir, this._repo);

  final Future<Directory> Function() _dir;
  final RouteMapRepository _repo;
  bool _busy = false;

  Future<File> get _file async => File(p.join((await _dir()).path, 'routes.json'));

  Future<List<List<List<num>>>> _read() async {
    try {
      final f = await _file;
      if (!await f.exists()) return [];
      return (jsonDecode(await f.readAsString()) as List)
          .map((r) => (r as List).map((pt) => (pt as List).cast<num>()).toList())
          .toList();
    } catch (_) {
      return [];
    }
  }

  Future<void> _write(List<List<List<num>>> routes) async => (await _file).writeAsString(jsonEncode(routes));

  Future<void> add(List<List<num>> points) async {
    if (points.length < 2) return;
    await _write([...await _read(), points]);
    await flush();
  }

  Future<int> flush() async {
    if (_busy) return 0;
    _busy = true;
    var sent = 0;
    try {
      final routes = await _read();
      while (routes.isNotEmpty) {
        try {
          await _repo.upload(routes.first);
          sent++;
        } on ApiException catch (e) {
          if (e.isNetwork || e.status == null || e.status! >= 500 || e.status == 429) break;
        }
        routes.removeAt(0); // sent, or refused for good
        await _write(routes);
      }
    } finally {
      _busy = false;
    }
    return sent;
  }
}

final routeOutboxProvider = Provider<RouteOutbox>((ref) => RouteOutbox(ref.watch(outboxDirProvider), ref.watch(routeMapRepositoryProvider)));
