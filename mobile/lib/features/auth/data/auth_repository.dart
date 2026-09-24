import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/security/device_identity.dart';
import '../../../core/storage/secure_store.dart';
import '../../profile/data/me.dart';

@immutable
class OtpRequestResult {
  const OtpRequestResult({required this.expiresIn, required this.resendIn});

  final int expiresIn;
  final int resendIn;
}

class AuthRepository {
  AuthRepository(this._api, this._store, this._device);

  final ApiClient _api;
  final SecureStore _store;
  final DeviceIdentity _device;

  Future<String?> readToken() => _store.read(SecureStore.kToken);

  Future<OtpRequestResult> requestOtp(String phone) => _withDevice(() async {
        final r = await _api.post('/auth/otp/request', data: {'phone': phone}, options: Req.anonymous(Req.signed()));
        final d = r['data'] as Map<String, dynamic>;
        return OtpRequestResult(expiresIn: d['expires_in'] as int, resendIn: d['resend_in'] as int);
      });

  Future<(Me, bool)> verifyOtp({required String phone, required String code, String? referralCode}) => _withDevice(() async {
        final r = await _api.post('/auth/otp/verify', options: Req.anonymous(Req.signed()), data: {
          'phone': phone,
          'code': code,
          'timezone': 'Asia/Tehran',
          if (referralCode != null && referralCode.isNotEmpty) 'referral_code': referralCode,
        });
        final d = r['data'] as Map<String, dynamic>;
        await _store.write(SecureStore.kToken, d['token'] as String);
        return (Me.fromJson(d['user'] as Map<String, dynamic>), d['is_new_user'] == true);
      });

  Future<Me> fetchMe() async {
    final r = await _api.get('/me');
    return Me.fromJson(r['data'] as Map<String, dynamic>);
  }

  Future<void> logout() async {
    try {
      await _api.post('/auth/logout');
    } on ApiException {
      // Logging out locally must always succeed, even offline.
    }
    await clearLocalSession();
  }

  Future<void> clearLocalSession() => _store.delete(SecureStore.kToken);

  /// Ensures a registered device, and transparently re-registers once if the
  /// server has forgotten it.
  Future<T> _withDevice<T>(Future<T> Function() call) async {
    await _device.ensureRegistered();
    try {
      return await call();
    } on ApiException catch (e) {
      if (e.code != 'device_not_registered') rethrow;
      await _device.reRegister();
      return call();
    }
  }
}

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(ref.watch(apiClientProvider), ref.watch(secureStoreProvider), ref.watch(deviceIdentityProvider)),
);
