import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/storage/secure_store.dart';
import '../../config/data/app_config.dart';
import '../../profile/data/me.dart';
import '../data/auth_repository.dart';

sealed class SessionState {
  const SessionState();
}

class SessionUnauthenticated extends SessionState {
  const SessionUnauthenticated({required this.onboardingDone});

  final bool onboardingDone;
}

class SessionAuthenticated extends SessionState {
  const SessionAuthenticated(this.me);

  final Me me;
}

/// The account exists but may not use the app (suspended / banned / device blocked).
class SessionBlocked extends SessionState {
  const SessionBlocked(this.error);

  final ApiException error;
}

class SessionController extends AsyncNotifier<SessionState> {
  AuthRepository get _repo => ref.read(authRepositoryProvider);
  SecureStore get _store => ref.read(secureStoreProvider);

  @override
  Future<SessionState> build() async {
    ref.read(apiClientProvider).onKeyRotationRequired = () => ref.read(deviceIdentityProvider).rotateKey();
    ref.read(apiClientProvider).onUnauthorized = (_) async {
      await _repo.clearLocalSession();
      state = AsyncData(SessionUnauthenticated(onboardingDone: await _onboardingDone()));
    };

    final token = await _repo.readToken();
    if (token == null) return SessionUnauthenticated(onboardingDone: await _onboardingDone());

    try {
      final me = await _repo.fetchMe();
      unawaited(_rotateKeyIfOld());
      return SessionAuthenticated(me);
    } on ApiException catch (e) {
      if (e.status == 403) return SessionBlocked(e);
      if (e.isUnauthenticated) {
        await _repo.clearLocalSession();
        return SessionUnauthenticated(onboardingDone: await _onboardingDone());
      }
      rethrow; // offline etc. → splash shows retry
    }
  }

  /// Keys older than the server's max age are replaced in the background.
  Future<void> _rotateKeyIfOld() async {
    try {
      final config = await ref.read(appConfigProvider.future);
      final days = (config.settings['security.device_key_max_age_days'] as num?)?.toInt() ?? 180;
      await ref.read(deviceIdentityProvider).rotateIfOld(days);
    } catch (_) {
      // Best effort: the next launch tries again.
    }
  }

  Future<bool> _onboardingDone() async => await _store.read(SecureStore.kOnboardingDone) == '1';

  Future<void> completeOnboarding() async {
    await _store.write(SecureStore.kOnboardingDone, '1');
    state = const AsyncData(SessionUnauthenticated(onboardingDone: true));
  }

  Future<bool> signIn({required String phone, required String code, String? referralCode}) async {
    final (me, isNew) = await _repo.verifyOtp(phone: phone, code: code, referralCode: referralCode);
    await _store.write(SecureStore.kOnboardingDone, '1');
    state = AsyncData(SessionAuthenticated(me));
    return isNew;
  }

  /// Replace the cached user after profile edits.
  void updateMe(Me me) => state = AsyncData(SessionAuthenticated(me));

  Future<void> refreshMe() async {
    try {
      updateMe(await _repo.fetchMe());
    } on ApiException catch (e) {
      if (e.status == 403) state = AsyncData(SessionBlocked(e));
    }
  }

  Future<void> signOut() async {
    await _repo.logout();
    state = const AsyncData(SessionUnauthenticated(onboardingDone: true));
  }

  Future<void> retry() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(build);
  }
}

final sessionProvider = AsyncNotifierProvider<SessionController, SessionState>(SessionController.new);

/// Convenience: the signed-in user (throws if called outside the authenticated shell).
final meProvider = Provider<Me>((ref) {
  final s = ref.watch(sessionProvider).value;
  if (s is SessionAuthenticated) return s.me;
  throw StateError('meProvider read outside an authenticated session');
});
