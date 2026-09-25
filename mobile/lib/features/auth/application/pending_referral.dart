import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/config/env.dart';
import '../../../core/providers.dart';

/// A referral code that arrived before sign-up: from an invite link (`/r/CODE`, App Link or
/// `gamyar://app/r/CODE`) or, for Play installs, from the install referrer. It pre-fills
/// the referral field on the OTP page and is cleared once used.
class PendingReferral extends Notifier<String?> {
  static const _key = 'pending_referral';
  static const _checkedKey = 'install_referrer_checked';
  static const _channel = MethodChannel('ir.gamyar.app/install_referrer');

  @override
  String? build() {
    _restore();
    return null;
  }

  Future<void> _restore() async {
    final store = ref.read(secureStoreProvider);
    final saved = await store.read(_key);
    if (saved != null && state == null) state = saved;
    if (Env.store == 'play' && await store.read(_checkedKey) == null) {
      await store.write(_checkedKey, '1');
      try {
        final referrer = await _channel.invokeMethod<String>('get');
        final code = codeFromInstallReferrer(referrer);
        if (code != null && state == null) set(code);
      } on PlatformException catch (_) {
      } on MissingPluginException catch (_) {}
    }
  }

  void set(String code) {
    state = code;
    ref.read(secureStoreProvider).write(_key, code);
  }

  void clear() {
    state = null;
    ref.read(secureStoreProvider).delete(_key);
  }
}

final pendingReferralProvider = NotifierProvider<PendingReferral, String?>(PendingReferral.new);

final _codePattern = RegExp(r'^[A-Za-z0-9]{4,12}$');

/// `/r/ABCD1234` → `ABCD1234`.
String? referralCodeFromPath(String path) {
  final segments = Uri.parse(path).pathSegments;
  if (segments.length != 2 || segments.first != 'r' || !_codePattern.hasMatch(segments.last)) return null;
  return segments.last.toUpperCase();
}

/// Play install referrer (`code=ABCD1234&utm_…`) → `ABCD1234`.
@visibleForTesting
String? codeFromInstallReferrer(String? referrer) {
  if (referrer == null || referrer.isEmpty) return null;
  final code = Uri.splitQueryString(referrer)['code'];
  return code != null && _codePattern.hasMatch(code) ? code.toUpperCase() : null;
}
