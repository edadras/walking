// Renders every screen of the real app (real router, widgets, theme and
// Vazirmatn font) at phone size, fed with JSON captured from the demo backend.
//
//   SCREENSHOTS=1 flutter test test/screenshots/screens_test.dart
//
// PNGs are written to build/screenshots/. Skipped in the normal test run.
@Tags(['screenshots'])
library;

import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:dio/dio.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/app/app.dart';
import 'package:gamyar/app/router.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/network/api_exception.dart';
import 'package:gamyar/core/providers.dart';
import 'package:gamyar/core/storage/secure_store.dart';
import 'package:gamyar/core/widgets/net_image.dart';
import 'package:gamyar/features/activity/application/tracking_service.dart';
import 'package:gamyar/features/activity/data/session_queue.dart';
import 'package:gamyar/features/auth/presentation/phone_page.dart';
import 'package:gamyar/features/sponsors/application/location_source.dart';
import 'package:gamyar/features/sponsors/data/sponsor_repository.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';
import 'package:timezone/data/latest_10y.dart' as tz_data;

import '../features/activity/tracking_service_test.dart' show FakePlatform;
import '../helpers/test_app.dart';

final enabled = Platform.environment['SCREENSHOTS'] == '1';
final outDir = Directory('build/screenshots');
final data = jsonDecode(File('test/screenshots/fixtures.json').readAsStringSync()) as Map<String, dynamic>;
final fixtures = data['fixtures'] as Map<String, dynamic>;
final ids = data['ids'] as Map<String, dynamic>;

/// Serves captured responses; POSTs that screens make on open get plausible answers.
class FixtureApi extends ApiClient {
  FixtureApi(SecureStore store) : super(store: store, deviceKey: FakeDeviceKey(), appVersion: '1.0.0');

  Map<String, dynamic> _lookup(String path, Map<String, dynamic>? query) {
    final q = (query ?? const {}).entries.where((e) => e.value != null).toList()..sort((a, b) => a.key.compareTo(b.key));
    final key = q.isEmpty ? path : '$path?${q.map((e) => '${e.key}=${e.value}').join('&')}';
    final hit = fixtures[key] ?? fixtures[path];
    if (hit == null) throw ApiException(code: 'not_found', message: 'no fixture for $key');
    return jsonDecode(jsonEncode(hit)) as Map<String, dynamic>;
  }

  @override
  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query, Options? options}) async => _lookup(path, query);

  @override
  Future<Map<String, dynamic>> post(String path, {Object? data, Options? options}) async {
    if (path.startsWith('/visits/')) return _lookup('/visits/${ids['visit']}', null);
    if (path == '/ads/rewarded/start') {
      final ad = _lookup('/ads/placements/rewarded_default', null)['data'];
      return {'data': {'id': 'v1', 'status': 'started', 'ad': ad, 'reward_points': 10, 'points_awarded': 0, 'started_at': DateTime.now().toUtc().toIso8601String()}};
    }
    return {'data': null};
  }

  @override
  Future<Map<String, dynamic>> patch(String path, {Object? data, Options? options}) async => {'data': null};

  @override
  Future<Map<String, dynamic>> delete(String path, {Options? options}) async => {'data': null};
}

Future<void> loadFonts() async {
  final vazir = FontLoader('Vazirmatn');
  for (final w in ['Regular', 'Medium', 'SemiBold', 'Bold', 'ExtraBold']) {
    vazir.addFont(Future.value(ByteData.sublistView(File('assets/fonts/Vazirmatn-$w.ttf').readAsBytesSync())));
  }
  await vazir.load();
  final flutterRoot = Platform.environment['FLUTTER_ROOT'] ?? '/opt/sdk/flutter';
  final icons = FontLoader('MaterialIcons')
    ..addFont(Future.value(ByteData.sublistView(File('$flutterRoot/bin/cache/artifacts/material_fonts/MaterialIcons-Regular.otf').readAsBytesSync())));
  await icons.load();
}

void grantPermissions() {
  // permission_handler: every permission granted (1).
  TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(
    const MethodChannel('flutter.baseflow.com/permissions/methods'),
    (call) async => switch (call.method) {
      'checkPermissionStatus' => 1,
      'requestPermissions' => {for (final p in call.arguments as List) p: 1},
      'checkServiceStatus' => 1,
      _ => null,
    },
  );
}

void main() {
  setUpAll(() async {
    tz_data.initializeTimeZones();
    sqfliteFfiInit();
    await loadFonts();
    grantPermissions();
    outDir.createSync(recursive: true);
  });

  Future<void> shoot(WidgetTester tester, GlobalKey key, String name) async {
    for (var i = 0; i < 12; i++) {
      await tester.pump(const Duration(milliseconds: 150));
    }
    // Let image decodes (real async work) finish, then settle their fade-in.
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 400)));
    for (var i = 0; i < 4; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
    final boundary = key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
    await tester.runAsync(() async {
      final image = await boundary.toImage(pixelRatio: 3);
      final png = await image.toByteData(format: ui.ImageByteFormat.png);
      File('${outDir.path}/$name.png').writeAsBytesSync(png!.buffer.asUint8List());
    });
  }

  Future<(GlobalKey, ProviderContainer)> boot(WidgetTester tester, {required bool signedIn, bool onboarded = true}) async {
    tester.view.physicalSize = const Size(1170, 2532);
    tester.view.devicePixelRatio = 3;
    final store = MemorySecureStore();
    if (onboarded) await store.write(SecureStore.kOnboardingDone, '1');
    await store.write(SecureStore.kDeviceId, 'dev');
    if (signedIn) await store.write(SecureStore.kToken, 'token');
    final queue = await SessionQueue.open(path: inMemoryDatabasePath, factory: databaseFactoryFfiNoIsolate);
    final key = GlobalKey();
    await tester.pumpWidget(ProviderScope(
      overrides: [
        secureStoreProvider.overrideWithValue(store),
        deviceKeyProvider.overrideWithValue(FakeDeviceKey()),
        apiClientProvider.overrideWith((ref) => FixtureApi(ref.watch(secureStoreProvider))),
        stepPlatformProvider.overrideWithValue(FakePlatform()),
        sessionQueueProvider.overrideWithValue(Future.value(queue)),
        locationPermissionProvider.overrideWith((_) async => true),
        locationSourceProvider.overrideWithValue(_Here()),
        imageResolverProvider.overrideWithValue(_localImage),
      ],
      child: RepaintBoundary(key: key, child: const GamyarApp()),
    ));
    final container = ProviderScope.containerOf(tester.element(find.byType(GamyarApp)));
    for (var i = 0; i < 10; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
    return (key, container);
  }

  testWidgets('signed-in screens', (tester) async {
    final (key, c) = await boot(tester, signedIn: true);
    final router = c.read(routerProvider);
    final orders = (ids['orders'] as List).cast<String>();

    final screens = <String, String>{
      '01-home': '/home',
      '02-activity': '/activity',
      '03-session-detail': '/activity/session/${ids['session']}',
      '04-walk': '/walk',
      '05-rewards': '/rewards',
      '06-wallet': '/wallet',
      '07-store': '/store',
      '08-product-gift-card': '/store/gift-card-500k',
      '09-product-bottle': '/store/sport-bottle',
      '10-orders': '/orders',
      '11-order-digital': '/orders/${orders.last}',
      '12-order-shipped': '/orders/${orders.first}',
      '13-addresses': '/addresses',
      '14-address-new': '/addresses/new',
      '15-profile': '/profile',
      '16-profile-edit': '/profile/edit',
      '17-notification-settings': '/profile/notifications',
      '18-devices': '/profile/devices',
      '19-delete-account': '/profile/delete',
      '20-health': '/health',
      '21-weekly-report': '/weekly-report',
      '22-water': '/water',
      '23-leaderboard': '/leaderboard',
      '24-achievements': '/achievements',
      '25-referral': '/referral',
      '26-challenges': '/challenges',
      '27-challenge-detail': '/challenges/${ids['challenge']}',
      '28-inbox': '/notifications',
      '29-nearby': '/nearby',
      '30-campaign': '/campaigns/${ids['campaign']}',
      '31-visit': '/visits/${ids['visit']}',
      '32-coupons': '/coupons',
      '33-rewarded-ad': '/rewarded-ad',
      '34-support': '/support',
      '35-support-new': '/support/new',
      '36-support-ticket': '/support/${ids['ticket']}',
      '37-faq': '/faq',
      '38-page-terms': '/page/terms',
    };
    for (final MapEntry(key: name, value: route) in screens.entries) {
      router.go(route);
      await shoot(tester, key, name);
    }
    await tester.pumpWidget(const SizedBox());
    await tester.pump(const Duration(seconds: 1));
  }, skip: !enabled);

  testWidgets('signed-out screens', (tester) async {
    final (key, c) = await boot(tester, signedIn: false, onboarded: false);
    final router = c.read(routerProvider);
    await shoot(tester, key, '00a-onboarding');
    await c.read(secureStoreProvider).write(SecureStore.kOnboardingDone, '1');
    router.go('/auth/phone');
    await shoot(tester, key, '00b-phone');
    router.go('/auth/otp', extra: const OtpArgs(phone: '09120000001', resendIn: 45));
    await shoot(tester, key, '00c-otp');
    await tester.pumpWidget(const SizedBox());
    await tester.pump(const Duration(seconds: 1));
  }, skip: !enabled);
}

/// Server image URLs map to the demo seeder's files (same basenames), so the
/// fixtures render real product images with no network.
final _imageCache = <String, MemoryImage>{};
ImageProvider _localImage(String url) => _imageCache.putIfAbsent(url, () {
      final file = File('../backend/database/seeders/assets/products/${Uri.parse(url).pathSegments.last}');
      return MemoryImage(file.existsSync() ? file.readAsBytesSync() : Uint8List(0));
    });

class _Here implements LocationSource {
  @override
  Future<Fix> current() async => (lat: 35.7785, lng: 51.4135, accuracy: 8.0, mock: false);
}
