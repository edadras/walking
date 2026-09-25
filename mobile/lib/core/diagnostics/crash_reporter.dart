import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/env.dart';
import '../network/api_client.dart';
import '../providers.dart';

/// Sends uncaught errors to the first-party `/client-errors` endpoint. Only the
/// error type, message and top of the stack leave the device; the server scrubs
/// again and groups identical crashes. Silent in debug builds.
class CrashReporter {
  CrashReporter(this._api, {required this.appVersion, this.enabled = !kDebugMode});

  final ApiClient _api;
  final String appVersion;
  final bool enabled;
  final _seen = <String>{};

  /// A crash loop must not become a request loop.
  static const maxPerSession = 20;

  Future<void> report(Object error, StackTrace? stack, {bool fatal = false}) async {
    if (!enabled || _seen.length >= maxPerSession) return;
    final lines = (stack ?? StackTrace.empty).toString().split('\n').where((l) => l.trim().isNotEmpty).take(40).toList();
    final key = '${error.runtimeType}|${lines.take(3).join('|')}';
    if (!_seen.add(key)) return;
    try {
      await _api.post('/client-errors', data: {
        'type': error.runtimeType.toString(),
        'message': _clip(error.toString(), 2000),
        'stack': _clip(lines.join('\n'), 8000),
        'fatal': fatal,
        'app_version': appVersion,
        // Release builds are obfuscated per flavor: version + store pick the symbols file.
        'store': Env.store,
      });
    } catch (_) {
      // Never let reporting itself crash or recurse.
    }
  }

  static String _clip(String s, int max) => s.length <= max ? s : s.substring(0, max);
}

final crashReporterProvider = Provider<CrashReporter>(
  (ref) => CrashReporter(ref.watch(apiClientProvider), appVersion: ref.watch(appVersionProvider)),
);
