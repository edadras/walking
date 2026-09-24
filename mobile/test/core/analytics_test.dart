import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/analytics/analytics.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/network/api_exception.dart';
import 'package:gamyar/core/storage/secure_store.dart';

import '../helpers/test_app.dart';

class _Api extends ApiClient {
  _Api() : super(store: MemorySecureStore(), deviceKey: FakeDeviceKey(), appVersion: '1');
  final batches = <List<String>>[];
  bool fail = false;

  @override
  Future<Map<String, dynamic>> post(String path, {Object? data, Options? options}) async {
    expect(path, '/analytics/events');
    if (fail) throw const ApiException(code: 'network', message: 'offline');
    batches.add([for (final e in (data! as Map)['events'] as List) (e as Map)['name'] as String]);
    return const {};
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('events before sign-in wait, then go out in one batch', () async {
    final api = _Api();
    final a = Analytics(api, flushEvery: const Duration(hours: 1));
    addTearDown(a.dispose);

    a.track('onboarding_completed');
    a.track('permission_result', {'permission': 'activity', 'granted': true});
    await a.flush();
    expect(api.batches, isEmpty);

    a.enabled = true;
    await Future<void>.delayed(Duration.zero);
    expect(api.batches, [['onboarding_completed', 'permission_result']]);
  });

  test('batches at the threshold, keeps events across failures, drops them on sign-out', () async {
    final api = _Api();
    final a = Analytics(api, batchSize: 3, flushEvery: const Duration(hours: 1))..enabled = true;
    addTearDown(a.dispose);

    a..track('store_view')..track('product_view')..track('purchase');
    await Future<void>.delayed(Duration.zero);
    expect(api.batches.single, ['store_view', 'product_view', 'purchase']);

    api.fail = true;
    a.track('coupon_claimed');
    await a.flush();
    api.fail = false;
    await a.flush();
    expect(api.batches.last, ['coupon_claimed']);

    a.track('walking_started');
    a.enabled = false;
    a.enabled = true;
    await a.flush();
    expect(api.batches, hasLength(2), reason: 'a previous user\'s events never reach the next account');
  });

  test('the queue is bounded while offline', () {
    final a = Analytics(_Api(), flushEvery: const Duration(hours: 1));
    addTearDown(a.dispose);
    for (var i = 0; i < Analytics.maxQueue + 50; i++) {
      a.track('app_open');
    }
    expect(a.pending, Analytics.maxQueue);
  });
}
