import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/app/router.dart';
import 'package:gamyar/core/permissions/required_permissions.dart';
import 'package:gamyar/core/providers.dart';
import 'package:gamyar/core/storage/secure_store.dart';
import 'package:gamyar/core/widgets/app_button.dart';
import 'package:gamyar/features/permissions/presentation/permissions_page.dart';

import '../../helpers/test_app.dart';

class FakeDevice implements DevicePermissions {
  FakeDevice(this.statuses, {this.family});
  final Map<RequiredPermission, GateStatus> statuses;
  final String? family;
  final requested = <RequiredPermission>[];
  int settingsOpened = 0;
  int autostartOpened = 0;

  @override
  Future<GateStatus> status(RequiredPermission p) async => statuses[p]!;
  @override
  Future<GateStatus> request(RequiredPermission p) async {
    requested.add(p);
    return statuses[p] = GateStatus.granted;
  }

  @override
  Future<void> openSettings() async => settingsOpened++;
  @override
  Future<OemInfo> oem() async => (manufacturer: family ?? 'Google', family: family);
  @override
  Future<bool> openAutostart() async {
    autostartOpened++;
    return true;
  }
}

Map<RequiredPermission, GateStatus> all(GateStatus s) => {for (final p in RequiredPermission.values) p: s};

void main() {
  PermissionGate gate({GateStatus s = GateStatus.granted, String? family, bool confirmed = false}) =>
      PermissionGate(statuses: all(s), oem: (manufacturer: '', family: family), autostartConfirmed: confirmed);

  test('signed-in users are held at the gate until everything is granted', () {
    expect(signedInRedirect('/home', const AsyncLoading()), '/splash');
    expect(signedInRedirect('/splash', const AsyncLoading()), isNull);
    expect(signedInRedirect('/home', AsyncData(gate(s: GateStatus.denied))), '/permissions');
    expect(signedInRedirect('/store', AsyncData(gate(family: 'xiaomi'))), '/permissions', reason: 'autostart not confirmed');
    expect(signedInRedirect('/permissions', AsyncData(gate(s: GateStatus.denied))), isNull);
    expect(signedInRedirect('/permissions', AsyncData(gate())), '/home');
    expect(signedInRedirect('/auth/otp', AsyncData(gate())), '/home');
    expect(signedInRedirect('/store', AsyncData(gate())), isNull);
    expect(signedInRedirect('/home', AsyncError(Exception('x'), StackTrace.empty)), '/permissions');
  });

  Future<FakeDevice> pump(WidgetTester tester, FakeDevice device, {MemorySecureStore? store}) async {
    tester.view
      ..physicalSize = const Size(1080, 4200)
      ..devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(testApp(const PermissionsPage(), overrides: [
      devicePermissionsProvider.overrideWithValue(device),
      secureStoreProvider.overrideWithValue(store ?? MemorySecureStore()),
    ]));
    await tester.pumpAndSettle();
    return device;
  }

  bool continueEnabled(WidgetTester tester) =>
      tester.widget<AppButton>(find.ancestor(of: find.text('ورود به گام‌یار'), matching: find.byType(AppButton))).onPressed != null;

  testWidgets('each missing permission is requested; continue unlocks when all are granted', (tester) async {
    final device = await pump(tester, FakeDevice(all(GateStatus.denied)));
    expect(find.text('برای ادامه، همه موارد بالا باید فعال شوند.'), findsOneWidget);
    expect(continueEnabled(tester), isFalse);

    for (var i = 0; i < 3; i++) {
      await tester.tap(find.text('اجازه می‌دهم').first);
      await tester.pumpAndSettle();
    }
    expect(device.requested, RequiredPermission.values);
    expect(find.text('اجازه می‌دهم'), findsNothing);
    expect(continueEnabled(tester), isTrue);
  });

  testWidgets('a permanently denied permission sends the user to settings', (tester) async {
    final device = await pump(tester, FakeDevice({...all(GateStatus.granted), RequiredPermission.activity: GateStatus.permanentlyDenied}));
    await tester.tap(find.text('رفتن به تنظیمات'));
    await tester.pumpAndSettle();
    expect(device.settingsOpened, 1);
    expect(device.requested, isEmpty);
  });

  testWidgets('vendor autostart must be opened, then confirmed, and is remembered', (tester) async {
    final store = MemorySecureStore();
    final device = await pump(tester, FakeDevice(all(GateStatus.granted), family: 'xiaomi'), store: store);
    expect(find.textContaining('شیائومی'), findsOneWidget);
    expect(continueEnabled(tester), isFalse);

    await tester.tap(find.text('فعال کردم'));
    await tester.pumpAndSettle();
    expect(await store.read(PermissionGateController.kAutostartConfirmed), isNull, reason: 'disabled until settings were opened');

    await tester.tap(find.text('باز کردن تنظیمات'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('فعال کردم'));
    await tester.pumpAndSettle();
    expect(device.autostartOpened, 1);
    expect(await store.read(PermissionGateController.kAutostartConfirmed), '1');
    expect(continueEnabled(tester), isTrue);
  });
}
