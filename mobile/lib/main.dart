import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:timezone/data/latest_10y.dart' as tz_data;

import 'app/app.dart';
import 'core/providers.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  FlutterError.onError = (details) {
    FlutterError.presentError(details);
    // Phase 9: forward to crash reporting.
  };
  PlatformDispatcher.instance.onError = (error, stack) {
    if (kDebugMode) debugPrint('Uncaught: $error\n$stack');
    return true;
  };

  tz_data.initializeTimeZones();
  final info = await PackageInfo.fromPlatform();

  runApp(ProviderScope(
    overrides: [appVersionProvider.overrideWithValue(info.version)],
    child: const GamyarApp(),
  ));
}
