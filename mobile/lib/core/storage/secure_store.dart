import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Small key/value secrets (auth token, device id, sync sequence). Backed by
/// the Android Keystore through flutter_secure_storage.
class SecureStore {
  SecureStore([FlutterSecureStorage? storage]) : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const kToken = 'auth_token';
  static const kDeviceId = 'device_id';
  static const kInstallId = 'install_id';
  static const kOnboardingDone = 'onboarding_done';
  static const kClockOffset = 'clock_offset_s';

  Future<String?> read(String key) => _storage.read(key: key);

  Future<void> write(String key, String? value) =>
      value == null ? _storage.delete(key: key) : _storage.write(key: key, value: value);

  Future<void> delete(String key) => _storage.delete(key: key);
}

/// In-memory implementation for tests.
class MemorySecureStore extends SecureStore {
  MemorySecureStore([Map<String, String>? initial]) : _values = {...?initial}, super(const FlutterSecureStorage());

  final Map<String, String> _values;

  @override
  Future<String?> read(String key) async => _values[key];

  @override
  Future<void> write(String key, String? value) async => value == null ? _values.remove(key) : _values[key] = value;

  @override
  Future<void> delete(String key) async => _values.remove(key);
}
