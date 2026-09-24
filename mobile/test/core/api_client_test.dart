import 'dart:convert';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/network/api_exception.dart';
import 'package:gamyar/core/security/device_key.dart';
import 'package:gamyar/core/storage/secure_store.dart';

import '../helpers/test_app.dart';

/// Captures outgoing requests and replies with scripted responses.
class ScriptedAdapter implements HttpClientAdapter {
  ScriptedAdapter(this.responder);

  final ResponseBody Function(RequestOptions options, String body) responder;
  /// Snapshot of (method+path+headers, body) per request; headers are copied because
  /// Dio reuses the options object on retry.
  final requests = <(RequestOptions, String)>[];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<List<int>>? requestStream, Future<void>? cancelFuture) async {
    final bytes = requestStream == null ? <int>[] : (await requestStream.toList()).expand((e) => e).toList();
    final body = utf8.decode(bytes);
    requests.add((options.copyWith(headers: Map.of(options.headers)), body));
    return responder(options, body);
  }

  @override
  void close({bool force = false}) {}
}

ResponseBody json(int status, Object body) => ResponseBody.fromString(jsonEncode(body), status, headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    });

void main() {
  late MemorySecureStore store;
  late FakeDeviceKey key;

  ApiClient client(ScriptedAdapter adapter) {
    final dio = Dio()..httpClientAdapter = adapter;
    return ApiClient(store: store, deviceKey: key, appVersion: '1.0.0', baseUrl: 'https://api.test/api/v1', dio: dio);
  }

  setUp(() {
    store = MemorySecureStore({SecureStore.kDeviceId: '01hdevice', SecureStore.kToken: 'tok'});
    key = FakeDeviceKey();
  });

  test('signs exactly the bytes that are sent', () async {
    final adapter = ScriptedAdapter((_, _) => json(200, {'data': {}}));
    await client(adapter).post('/auth/otp/request', data: {'phone': '09121234567'}, options: Req.signed());

    final (options, body) = adapter.requests.single;
    final h = options.headers;
    expect(h['X-Device-Id'], '01hdevice');
    expect(h['Authorization'], 'Bearer tok');
    expect(h['X-Nonce'], matches(RegExp(r'^[a-f0-9]{32}$')));

    final canonical = canonicalRequest('POST', '/api/v1/auth/otp/request', h['X-Timestamp'] as String, h['X-Nonce'] as String,
        sha256.convert(utf8.encode(body)).toString());
    expect(key.signed.single, canonical);
    expect(h['X-Signature'], FakeDeviceKey.expectedSignature(canonical));
    expect(jsonDecode(body), {'phone': '09121234567'});
  });

  test('unsigned requests carry no signature headers; anonymous ones no token', () async {
    final adapter = ScriptedAdapter((_, _) => json(200, {'data': {}}));
    await client(adapter).get('/config', options: Req.anonymous());

    final h = adapter.requests.single.$1.headers;
    expect(h.containsKey('X-Signature'), isFalse);
    expect(h.containsKey('Authorization'), isFalse);
  });

  test('every nonce is unique', () async {
    final adapter = ScriptedAdapter((_, _) => json(200, {'data': {}}));
    final api = client(adapter);
    await api.post('/a', options: Req.signed());
    await api.post('/a', options: Req.signed());

    expect(adapter.requests[0].$1.headers['X-Nonce'], isNot(adapter.requests[1].$1.headers['X-Nonce']));
  });

  test('maps structured server errors to ApiException', () async {
    final adapter = ScriptedAdapter((_, _) => json(422, {
          'error': {'code': 'otp_invalid', 'message': 'کد وارد شده صحیح نیست.', 'context': {'remaining_attempts': 4}},
        }));

    await expectLater(
      client(adapter).post('/auth/otp/verify'),
      throwsA(isA<ApiException>()
          .having((e) => e.code, 'code', 'otp_invalid')
          .having((e) => e.message, 'message', 'کد وارد شده صحیح نیست.')
          .having((e) => e.context['remaining_attempts'], 'remaining', 4)),
    );
  });

  test('hides raw 500 bodies behind a Persian message', () async {
    final adapter = ScriptedAdapter((_, _) => ResponseBody.fromString('<html>Stack trace…</html>', 500));

    await expectLater(
      client(adapter).get('/me'),
      throwsA(isA<ApiException>().having((e) => e.message, 'message', ApiException.server.message)),
    );
  });

  test('recovers from clock skew by adopting server time and re-signing once', () async {
    final serverTime = DateTime.now().millisecondsSinceEpoch ~/ 1000 + 3600;
    var calls = 0;
    final adapter = ScriptedAdapter((options, _) {
      calls++;
      if (calls == 1) {
        return json(400, {
          'error': {'code': 'timestamp_skew', 'message': '…', 'context': {'server_time': serverTime}},
        });
      }
      return json(200, {'data': {'ok': true}});
    });

    final result = await client(adapter).post('/x', data: {'a': 1}, options: Req.signed());

    expect(result['data'], {'ok': true});
    expect(adapter.requests, hasLength(2));
    final retriedTs = int.parse(adapter.requests[1].$1.headers['X-Timestamp'] as String);
    expect((retriedTs - serverTime).abs(), lessThan(5));
    expect(adapter.requests[0].$1.headers['X-Nonce'], isNot(adapter.requests[1].$1.headers['X-Nonce']));
    expect(jsonDecode(adapter.requests[1].$2), {'a': 1});
    expect(await store.read(SecureStore.kClockOffset), isNotNull);
  });

  test('notifies on revoked credentials', () async {
    final adapter = ScriptedAdapter((_, _) => json(401, {'error': {'code': 'unauthenticated', 'message': '…'}}));
    final api = client(adapter);
    ApiException? seen;
    api.onUnauthorized = (e) async => seen = e;

    await expectLater(api.get('/me'), throwsA(isA<ApiException>()));
    expect(seen?.code, 'unauthenticated');
  });
}
