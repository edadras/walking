import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'network/api_client.dart';
import 'security/device_identity.dart';
import 'security/device_key.dart';
import 'storage/secure_store.dart';

/// Overridden in main() with the real value from package_info.
final appVersionProvider = Provider<String>((ref) => '1.0.0');

final secureStoreProvider = Provider<SecureStore>((ref) => SecureStore());

final deviceKeyProvider = Provider<DeviceKey>((ref) => KeystoreDeviceKey());

final apiClientProvider = Provider<ApiClient>((ref) => ApiClient(
      store: ref.watch(secureStoreProvider),
      deviceKey: ref.watch(deviceKeyProvider),
      appVersion: ref.watch(appVersionProvider),
    ));

final deviceIdentityProvider = Provider<DeviceIdentity>((ref) => DeviceIdentity(
      api: ref.watch(apiClientProvider),
      store: ref.watch(secureStoreProvider),
      key: ref.watch(deviceKeyProvider),
      appVersion: ref.watch(appVersionProvider),
    ));
