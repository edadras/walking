import 'dart:convert';
import 'dart:typed_data';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/network/cert_pinning.dart';
import 'package:gamyar/core/security/device_identity.dart';
import 'package:gamyar/core/storage/secure_store.dart';

import '../helpers/test_app.dart';

// Self-signed test certificates; pins computed with openssl:
//   openssl x509 -pubkey -noout | openssl pkey -pubin -outform der | openssl dgst -sha256 -binary | base64
const ecCert = 'MIIBijCCAS+gAwIBAgIUPpSvxCvRzJNjzJRORMEWk8sNXm0wCgYIKoZIzj0EAwIwGjEYMBYGA1UEAwwPYXBpLmdhbXlhci50ZXN0MB4XDTI2MDkyNDIxMDAxOFoXDTM2MDkyMTIxMDAxOFowGjEYMBYGA1UEAwwPYXBpLmdhbXlhci50ZXN0MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE1wJQnBKgprN9dFg/ozC1yMCENDmbisfK6ilwg43t7BxfC9X8cBa7pX7m5Qd2gOvWeGwOMaJylL1E+7ekxNFQ0aNTMFEwHQYDVR0OBBYEFDO06A7Tip/fDaAxWmN8CTEhRZp0MB8GA1UdIwQYMBaAFDO06A7Tip/fDaAxWmN8CTEhRZp0MA8GA1UdEwEB/wQFMAMBAf8wCgYIKoZIzj0EAwIDSQAwRgIhAIKxr2GgV7Q12nMz+RT2Gsohc49gZ9MQxE4L12fhuAVKAiEAiLBcqMzvC4XrC9d2ZGuZc25BA5oJjuVcfSPdBHiCZM8=';
const ecPin = 'nsUHmB8kzPTAxjPyh6B9lT4hyLLmRPFTdKaK33HuoZI=';
const rsaCert = 'MIIDFTCCAf2gAwIBAgIUFCe7VMaWUHCWy+jtqwDLlUWCszIwDQYJKoZIhvcNAQELBQAwGjEYMBYGA1UEAwwPcnNhLmdhbXlhci50ZXN0MB4XDTI2MDkyNDIxMDAxOFoXDTM2MDkyMTIxMDAxOFowGjEYMBYGA1UEAwwPcnNhLmdhbXlhci50ZXN0MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAy0hid1yVNfGPND2OS8W0zaaALPFsyG9jxXKlYr5ekU9bSiESuB3a0tXyInz09FHwVQ8KXANGwmeHgLmZNDE1HoaxomP6U6+CEicOilE0sGsHzrWYZR5/01EwBM6f81LLsfsKV+qUnyG1DTKEedauyD2WRLyHFphPIDFXaowYpcqUTaCmptLgMfWz10yc2/TqD51RyWV1KRRIAx28UKTmN20WijzWmkWfYI5hTfFmXTumpWbZLm2D3rKz5AfQSpuK4x5VijrBEyntdLlns0Gyz0AyeKoTPdsYpCf/LPn53zOmhrzRI/5RddNoSEyxAPWv31HCIDGPlZyUENU3avYcRwIDAQABo1MwUTAdBgNVHQ4EFgQUQIx5WlmVKqZg394rwhBiJ/mc6fMwHwYDVR0jBBgwFoAUQIx5WlmVKqZg394rwhBiJ/mc6fMwDwYDVR0TAQH/BAUwAwEB/zANBgkqhkiG9w0BAQsFAAOCAQEAoF9yAxogPmYnOHiqIrUl83Yefonp9bgOgMN2nHXD1Pf0pkl7Fiy7neTPiEHLWsdlfp+ZMJLYTjxP5Q9F2l+pWOFEC4GNKgqAZV7LjMdrOgQtxbRM8ZpsX8IBz6IQB4O8fvJmzFtkpl0xKJls/nak/Wn3ziGFSHwgMVRRAPWo6jNPRXOxq0ZaydoIIbzDHuUXAno+s0wQfl/Prb78mAGvW43S8E4NmejUVKrcYm0Pl+384MHBzYltQClYhzfNjmZKRTjsNlsx46fSFvqdIdQPyp8m20mUWDGGT9OlLVkvPSnnQfQcYxGgtVJ3EG/tYWl4Vn+ahyl9x0FeBja4Bzlxlw==';
const rsaPin = 'E0t0cWH9+NkDGqPDQGo/1M+t29ZOpalqy8HHMzVfnC8=';

/// Captures requests instead of sending them.
class CapturingAdapter implements HttpClientAdapter {
  final requests = <RequestOptions>[];
  final responses = <ResponseBody Function(RequestOptions)>[];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    requests.add(options);
    return responses.removeAt(0)(options);
  }

  @override
  void close({bool force = false}) {}
}

ResponseBody json(int status, Object body) =>
    ResponseBody.fromString(jsonEncode(body), status, headers: {Headers.contentTypeHeader: [Headers.jsonContentType]});

void main() {
  group('certificate pinning', () {
    test('SPKI hash matches openssl for EC and RSA certificates', () {
      expect(CertPinning.spkiSha256(base64Decode(ecCert)), ecPin);
      expect(CertPinning.spkiSha256(base64Decode(rsaCert)), rsaPin);
    });

    test('malformed certificates are rejected, not crashed on', () {
      expect(() => CertPinning.spkiSha256(Uint8List.fromList([0x30, 0x82, 0xFF])), throwsFormatException);
    });

    test('pins install a validating adapter; no pins leave dio untouched', () {
      final plain = Dio();
      final adapter = plain.httpClientAdapter;
      expect(identical(CertPinning.pinned(plain, const []).httpClientAdapter, adapter), isTrue);
      expect(CertPinning.pinned(Dio(), [ecPin]).httpClientAdapter, isA<IOHttpClientAdapter>());
    });
  });

  group('device key rotation', () {
    late MemorySecureStore store;
    late FakeDeviceKey key;
    late CapturingAdapter adapter;
    late ApiClient api;
    late DeviceIdentity identity;

    setUp(() async {
      store = MemorySecureStore();
      await store.write(SecureStore.kDeviceId, 'dev-1');
      key = FakeDeviceKey();
      adapter = CapturingAdapter();
      api = ApiClient(store: store, deviceKey: key, appVersion: '1', dio: Dio()..httpClientAdapter = adapter);
      identity = DeviceIdentity(api: api, store: store, key: key, appVersion: '1');
    });

    test('proof binds device, the exact signed timestamp and the new key; commits only on success', () async {
      adapter.responses.add((_) => json(200, {'data': {'device_id': 'dev-1', 'key_version': 2}}));

      expect(await identity.rotateKey(), isTrue);

      final req = adapter.requests.single;
      final body = jsonDecode(req.data as String) as Map<String, dynamic>;
      final timestamp = req.headers['X-Timestamp'] as String;
      final newKey = body['public_key'] as String;
      expect(req.path, endsWith('/devices/rotate-key'));
      expect(req.headers['X-Signature'], isNotNull, reason: 'signed with the current key');
      final statement = 'gamyar-key-rotation\ndev-1\n$timestamp\n${sha256Hex(base64Decode(newKey))}';
      expect(body['proof'], await key.signPending(Uint8List.fromList(utf8.encode(statement))));
      expect(key.committed, isTrue);
    });

    test('a refused rotation keeps the old key', () async {
      adapter.responses.add((_) => json(409, {'error': {'code': 'key_in_use', 'message': 'x'}}));
      expect(await identity.rotateKey(), isFalse);
      expect(key.committed, isFalse);
      expect(key.hasPending, isFalse);
    });

    test('key_rotation_required triggers one rotation and a retry', () async {
      api.onKeyRotationRequired = identity.rotateKey;
      adapter.responses
        ..add((_) => json(428, {'error': {'code': 'key_rotation_required', 'message': 'x'}}))
        ..add((_) => json(200, {'data': {'device_id': 'dev-1', 'key_version': 2}}))
        ..add((_) => json(200, {'data': {'ok': true}}));

      final r = await api.post('/me/deletion-request', options: Req.signed());

      expect(r['data'], {'ok': true});
      expect(adapter.requests.map((r) => r.path.split('/').last), ['deletion-request', 'rotate-key', 'deletion-request']);
    });

    test('old keys are rotated on schedule only', () async {
      key.createdAt = DateTime.now().subtract(const Duration(days: 10));
      await identity.rotateIfOld(180);
      expect(adapter.requests, isEmpty);

      key.createdAt = DateTime.now().subtract(const Duration(days: 200));
      adapter.responses.add((_) => json(200, {'data': {'device_id': 'dev-1', 'key_version': 2}}));
      await identity.rotateIfOld(180);
      expect(adapter.requests, hasLength(1));
    });
  });
}

String sha256Hex(List<int> bytes) => sha256.convert(bytes).toString();
