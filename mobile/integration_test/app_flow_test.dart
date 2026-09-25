// End-to-end on an Android emulator against a real backend (see .github/workflows/ci.yml, job "e2e"):
// real Keystore device key + signed requests, real OTP flow (reserved load-test number), wallet,
// a points purchase and a cash-out request confirmed with an SMS code.
//
//   php artisan db:seed --class=DemoSeeder && php artisan e2e:prepare 09990000001   (LOADTEST_OTP_CODE=12345)
//   flutter test integration_test/app_flow_test.dart --flavor play \
//     --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1 --dart-define=E2E_OTP=12345
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/app/app.dart';
import 'package:gamyar/app/router.dart';
import 'package:gamyar/core/permissions/required_permissions.dart';
import 'package:gamyar/core/providers.dart';
import 'package:gamyar/l10n/app_localizations.dart';
import 'package:integration_test/integration_test.dart';
import 'package:timezone/data/latest_10y.dart' as tz_data;

const _phone = String.fromEnvironment('E2E_PHONE', defaultValue: '09990000001');
const _otp = String.fromEnvironment('E2E_OTP', defaultValue: '12345');

/// System permission dialogs can't be driven from Flutter; the gate itself is covered by widget tests.
class _Granted implements DevicePermissions {
  @override
  Future<GateStatus> status(RequiredPermission p) async => GateStatus.granted;
  @override
  Future<GateStatus> request(RequiredPermission p) async => GateStatus.granted;
  @override
  Future<void> openSettings() async {}
  @override
  Future<OemInfo> oem() async => (manufacturer: 'Google', family: null);
  @override
  Future<bool> openAutostart() async => false;
}

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  final l = lookupAppLocalizations(const Locale('fa'));

  /// Waits for real network I/O (pumpAndSettle can't see pending HTTP).
  Future<void> waitFor(WidgetTester tester, Finder finder, {Duration timeout = const Duration(seconds: 30)}) async {
    final end = DateTime.now().add(timeout);
    while (DateTime.now().isBefore(end)) {
      await tester.pump(const Duration(milliseconds: 250));
      if (finder.evaluate().isNotEmpty) return;
    }
    throw TestFailure('Timed out waiting for $finder');
  }

  Future<Finder> waitForAny(WidgetTester tester, List<Finder> finders) async {
    final end = DateTime.now().add(const Duration(seconds: 45));
    while (DateTime.now().isBefore(end)) {
      await tester.pump(const Duration(milliseconds: 250));
      for (final f in finders) {
        if (f.evaluate().isNotEmpty) return f;
      }
    }
    throw TestFailure('Timed out waiting for any of $finders');
  }

  Future<void> tapText(WidgetTester tester, String text) async {
    final f = find.text(text).last;
    await tester.ensureVisible(f);
    await tester.pump(const Duration(milliseconds: 300));
    await tester.tap(f);
    await tester.pump(const Duration(milliseconds: 300));
  }

  testWidgets('sign in, see balance, buy with points, request a cash-out', (tester) async {
    tz_data.initializeTimeZones();
    final container = ProviderContainer(overrides: [
      appVersionProvider.overrideWithValue('1.0.0'),
      devicePermissionsProvider.overrideWithValue(_Granted()),
    ]);
    addTearDown(container.dispose);
    await tester.pumpWidget(UncontrolledProviderScope(container: container, child: const GamyarApp()));

    // Onboarding → phone → OTP (registers the device and signs every request with the Keystore key).
    // Onboarding slides: "continue" until the last one offers "start".
    await waitForAny(tester, [find.text(l.commonContinue), find.text(l.onboardingStart), find.text(l.authSendCode)]);
    for (var i = 0; i < 10 && find.text(l.commonContinue).evaluate().isNotEmpty; i++) {
      await tapText(tester, l.commonContinue);
      await tester.pump(const Duration(milliseconds: 800));
    }
    if (find.text(l.onboardingStart).evaluate().isNotEmpty) await tapText(tester, l.onboardingStart);
    await waitFor(tester, find.text(l.authSendCode));
    await tester.enterText(find.byType(TextField).first, _phone);
    await tapText(tester, l.authSendCode);
    await waitFor(tester, find.text(l.authOtpTitle));
    await tester.enterText(find.byType(TextField).first, _otp);
    await waitFor(tester, find.text(l.navHome), timeout: const Duration(seconds: 45));

    final router = container.read(routerProvider);

    // Wallet shows the server balance.
    router.go('/wallet');
    await waitFor(tester, find.text('۶۰٬۰۰۰'));

    // Buy a service product with points.
    router.go('/store/plant-a-tree');
    await waitFor(tester, find.text(l.storeBuy));
    await tapText(tester, l.storeBuy);
    await waitFor(tester, find.text(l.checkoutTitle));
    await tapText(tester, l.checkoutConfirm('۵۰۰'));
    await tester.pump(const Duration(seconds: 3)); // order placed server-side
    router.go('/wallet');
    await waitFor(tester, find.text('۵۹٬۵۰۰'));

    // Cash-out: amount within limits, confirmed with the SMS code.
    router.go('/cashout');
    await waitFor(tester, find.text(l.cashoutSubmit));
    await tester.enterText(find.byType(TextField).first, '10000');
    await tapText(tester, l.cashoutSubmit);
    await waitFor(tester, find.text(l.cashoutOtpTitle));
    await tester.enterText(find.byType(TextField).last, _otp);
    // Server accepted it (the history row below may be off-screen on small displays).
    await waitFor(tester, find.text(l.cashoutSubmitted));
  });
}
