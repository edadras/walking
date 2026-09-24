import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/diagnostics/crash_reporter.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/network/api_exception.dart';
import 'package:gamyar/core/storage/secure_store.dart';

import '../helpers/test_app.dart';

class _Api extends ApiClient {
  _Api() : super(store: MemorySecureStore(), deviceKey: FakeDeviceKey(), appVersion: '1');
  final sent = <Map<String, Object?>>[];
  bool fail = false;

  @override
  Future<Map<String, dynamic>> post(String path, {Object? data, Options? options}) async {
    expect(path, '/client-errors');
    if (fail) throw const ApiException(code: 'network', message: 'offline');
    sent.add(Map<String, Object?>.from(data! as Map));
    return const {};
  }
}

void main() {
  test('reports once per distinct crash, with type, trimmed stack and version', () async {
    final api = _Api();
    final r = CrashReporter(api, appVersion: '1.4.0', enabled: true);
    final stack = StackTrace.fromString(List.generate(60, (i) => '#$i frame$i (package:gamyar/x.dart:$i:1)').join('\n'));

    await r.report(StateError('boom'), stack, fatal: true);
    await r.report(StateError('boom again'), stack, fatal: true);
    await r.report(const FormatException('bad'), StackTrace.fromString('#0 other'));

    expect(api.sent, hasLength(2));
    expect(api.sent.first['type'], 'StateError');
    expect(api.sent.first['fatal'], true);
    expect(api.sent.first['app_version'], '1.4.0');
    expect((api.sent.first['stack']! as String).split('\n'), hasLength(40));
  });

  test('never throws and stops after the per-session cap', () async {
    final api = _Api()..fail = true;
    final r = CrashReporter(api, appVersion: '1', enabled: true);
    await expectLater(r.report(Exception('x'), null), completes);

    api.fail = false;
    for (var i = 0; i < CrashReporter.maxPerSession + 5; i++) {
      await r.report(Exception('e$i'), StackTrace.fromString('#0 f$i'));
    }
    expect(api.sent.length, CrashReporter.maxPerSession - 1);
  });

  test('disabled reporters send nothing', () async {
    final api = _Api();
    await CrashReporter(api, appVersion: '1', enabled: false).report(Exception('x'), null);
    expect(api.sent, isEmpty);
  });
}
