import 'dart:convert';
import 'dart:io';

import 'package:crypto/crypto.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:uuid/uuid.dart';

import '../config/env.dart';
import '../network/api_client.dart';
import '../storage/secure_store.dart';
import 'device_key.dart';

/// Registers this installation with the server exactly once and keeps its
/// server-issued id. Registration is signed with the key it registers.
class DeviceIdentity {
  DeviceIdentity({required this.api, required this.store, required this.key, required this.appVersion, DeviceInfoPlugin? info})
      : _info = info ?? DeviceInfoPlugin();

  final ApiClient api;
  final SecureStore store;
  final DeviceKey key;
  final String appVersion;
  final DeviceInfoPlugin _info;

  Future<String>? _inFlight;

  Future<String?> get deviceId => store.read(SecureStore.kDeviceId);

  /// Returns the device id, registering first if needed. Concurrent callers share one registration.
  Future<String> ensureRegistered() async {
    final existing = await store.read(SecureStore.kDeviceId);
    if (existing != null) return existing;
    return _inFlight ??= _register().whenComplete(() => _inFlight = null);
  }

  /// Server no longer knows this device (e.g. DB reset): forget it and register again.
  Future<String> reRegister() async {
    await store.delete(SecureStore.kDeviceId);
    return ensureRegistered();
  }

  Future<String> _register() async {
    var installId = await store.read(SecureStore.kInstallId);
    if (installId == null) {
      installId = const Uuid().v4();
      await store.write(SecureStore.kInstallId, installId);
    }

    final publicKey = await key.publicKey();
    final signals = await key.signals();
    final fingerprint = sha256.convert(base64Decode(publicKey)).toString();
    final requestHash = sha256.convert(utf8.encode('$installId|$fingerprint')).toString();
    final integrity = await key.integrityToken(requestHash, Env.integrityCloudProjectNumber);

    final meta = await _deviceMeta();
    final response = await api.post('/devices/register', options: Req.anonymous(Req.signed()), data: {
      'install_id': installId,
      'platform': Env.platform,
      'app_version': appVersion,
      'public_key': publicKey,
      'integrity_token': ?integrity,
      'emulator_suspected': signals.emulator,
      'root_suspected': signals.rooted,
      ...meta,
    });

    final data = response['data'] as Map<String, dynamic>;
    final deviceId = data['device_id'] as String;
    await store.write(SecureStore.kDeviceId, deviceId);
    if (data['server_time'] is int) await api.syncClock(data['server_time'] as int);
    return deviceId;
  }

  Future<Map<String, String>> _deviceMeta() async {
    if (!Platform.isAndroid) return const {};
    final a = await _info.androidInfo;
    return {
      'os_version': a.version.release,
      'model': a.model.length > 64 ? a.model.substring(0, 64) : a.model,
      'manufacturer': a.manufacturer.length > 64 ? a.manufacturer.substring(0, 64) : a.manufacturer,
    };
  }
}
