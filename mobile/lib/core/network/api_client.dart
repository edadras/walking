import 'dart:convert';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:uuid/uuid.dart';

import '../config/env.dart';
import '../security/device_key.dart';
import '../storage/secure_store.dart';
import 'api_exception.dart';
import 'cert_pinning.dart';

/// Per-request options understood by the interceptors.
abstract final class Req {
  /// Sign with the Keystore device key (sensitive operations).
  static Options signed([Options? base]) => (base ?? Options()).copyWith(extra: {...?base?.extra, 'signed': true});

  /// Don't attach the bearer token.
  static Options anonymous([Options? base]) => (base ?? Options()).copyWith(extra: {...?base?.extra, 'noAuth': true});
}

typedef UnauthorizedHandler = Future<void> Function(ApiException error);

/// Rotates the device key when the server demands it; returns true on success.
typedef KeyRotationHandler = Future<bool> Function();

/// Thin wrapper over Dio: base headers, auth, request signing, clock-skew
/// recovery and mapping every failure to [ApiException].
class ApiClient {
  ApiClient({
    required SecureStore store,
    required DeviceKey deviceKey,
    required String appVersion,
    String baseUrl = Env.apiBaseUrl,
    Dio? dio,
  })  : _store = store, // ignore: prefer_initializing_formals
        _deviceKey = deviceKey, // ignore: prefer_initializing_formals
        _appVersion = appVersion, // ignore: prefer_initializing_formals
        dio = dio ?? CertPinning.pinned(Dio(), Env.certPinList) {
    this.dio.options
      ..baseUrl = baseUrl
      ..connectTimeout = const Duration(seconds: 10)
      ..receiveTimeout = const Duration(seconds: 20)
      ..sendTimeout = const Duration(seconds: 20)
      ..responseType = ResponseType.json
      ..headers = {'Accept': 'application/json', 'Accept-Language': 'fa'};
    this.dio.interceptors.add(InterceptorsWrapper(onRequest: _onRequest, onError: _onError));
  }

  final Dio dio;
  final SecureStore _store;
  final DeviceKey _deviceKey;
  final String _appVersion;
  final _uuid = const Uuid();

  /// Called when the server rejects our credentials (token expired/revoked, device mismatch).
  UnauthorizedHandler? onUnauthorized;

  KeyRotationHandler? onKeyRotationRequired;

  int _clockOffset = 0;
  bool _clockLoaded = false;

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query, Options? options}) =>
      _send(() => dio.get<dynamic>(path, queryParameters: query, options: options));

  Future<Map<String, dynamic>> post(String path, {Object? data, Options? options}) =>
      _send(() => dio.post<dynamic>(path, data: data ?? const <String, dynamic>{}, options: options));

  Future<Map<String, dynamic>> patch(String path, {Object? data, Options? options}) =>
      _send(() => dio.patch<dynamic>(path, data: data ?? const <String, dynamic>{}, options: options));

  Future<Map<String, dynamic>> put(String path, {Object? data, Options? options}) =>
      _send(() => dio.put<dynamic>(path, data: data ?? const <String, dynamic>{}, options: options));

  Future<Map<String, dynamic>> delete(String path, {Options? options}) =>
      _send(() => dio.delete<dynamic>(path, options: options));

  Future<Map<String, dynamic>> _send(Future<Response<dynamic>> Function() call) async {
    try {
      final response = await call();
      final body = response.data;
      return body is Map<String, dynamic> ? body : const {};
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// Seconds to add to the device clock so signed timestamps match the server.
  Future<int> serverTimeNow() async {
    if (!_clockLoaded) {
      _clockOffset = int.tryParse(await _store.read(SecureStore.kClockOffset) ?? '') ?? 0;
      _clockLoaded = true;
    }
    return DateTime.now().millisecondsSinceEpoch ~/ 1000 + _clockOffset;
  }

  Future<void> syncClock(int serverTime) async {
    _clockOffset = serverTime - DateTime.now().millisecondsSinceEpoch ~/ 1000;
    _clockLoaded = true;
    await _store.write(SecureStore.kClockOffset, '$_clockOffset');
  }

  Future<void> _onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    try {
      options.headers['X-App-Version'] = _appVersion;
      options.headers['X-Platform'] = Env.platform;

      final deviceId = await _store.read(SecureStore.kDeviceId);
      if (deviceId != null) options.headers['X-Device-Id'] = deviceId;

      if (options.extra['noAuth'] != true) {
        final token = await _store.read(SecureStore.kToken);
        if (token != null) options.headers['Authorization'] = 'Bearer $token';
      }

      if (options.extra['signed'] == true) await _sign(options);

      handler.next(options);
    } catch (e) {
      handler.reject(DioException(requestOptions: options, error: ApiException.unknown));
    }
  }

  /// The body is serialised here, once, so the bytes we sign are exactly the bytes sent.
  Future<void> _sign(RequestOptions options) async {
    final String body;
    if (options.data == null || (options.method == 'GET' || options.method == 'DELETE') && options.data is Map && (options.data as Map).isEmpty) {
      body = '';
      options.data = null;
    } else {
      body = options.data is String ? options.data as String : jsonEncode(options.data);
      options.data = body;
      options.contentType = Headers.jsonContentType;
    }

    // Key rotation signs a proof over the exact timestamp it will send.
    final timestamp = options.extra['timestamp'] as String? ?? '${await serverTimeNow()}';
    final nonce = _uuid.v4().replaceAll('-', '');
    final path = options.uri.path;
    final canonical = canonicalRequest(options.method, path, timestamp, nonce, sha256.convert(utf8.encode(body)).toString());

    options.headers['X-Timestamp'] = timestamp;
    options.headers['X-Nonce'] = nonce;
    options.headers['X-Signature'] = await _deviceKey.sign(utf8Bytes(canonical));
  }

  Future<void> _onError(DioException e, ErrorInterceptorHandler handler) async {
    final error = ApiException.fromDio(e);
    final options = e.requestOptions;

    // Device clock is off: adopt the server's time and retry once with a fresh signature.
    if (error.code == 'timestamp_skew' && options.extra['retried'] != true && error.context['server_time'] is int) {
      await syncClock(error.context['server_time'] as int);
      options.extra['retried'] = true;
      try {
        final retry = await dio.fetch<dynamic>(options..data = options.data is String ? jsonDecode(options.data as String) : options.data);
        return handler.resolve(retry);
      } on DioException catch (retryError) {
        return handler.next(retryError);
      }
    }

    // Server requires a fresh device key (suspected compromise): rotate, then retry once.
    if (error.code == 'key_rotation_required' && options.extra['rotated'] != true && onKeyRotationRequired != null) {
      if (await onKeyRotationRequired!()) {
        options.extra['rotated'] = true;
        try {
          final retry = await dio.fetch<dynamic>(options..data = options.data is String ? jsonDecode(options.data as String) : options.data);
          return handler.resolve(retry);
        } on DioException catch (retryError) {
          return handler.next(retryError);
        }
      }
    }

    if ((error.code == 'unauthenticated' || error.code == 'device_mismatch') && options.extra['noAuth'] != true) {
      await onUnauthorized?.call(error);
    }

    handler.next(DioException(requestOptions: options, response: e.response, type: e.type, error: error));
  }
}
