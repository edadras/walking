import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:timezone/data/latest_10y.dart' as tz_data;

import 'app/app.dart';
import 'core/diagnostics/crash_reporter.dart';
import 'core/providers.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  tz_data.initializeTimeZones();
  final info = await PackageInfo.fromPlatform();
  final container = ProviderContainer(overrides: [appVersionProvider.overrideWithValue(info.version)]);
  final crashes = container.read(crashReporterProvider);

  FlutterError.onError = (details) {
    FlutterError.presentError(details);
    unawaited(crashes.report(details.exception, details.stack, fatal: false));
  };
  PlatformDispatcher.instance.onError = (error, stack) {
    if (kDebugMode) debugPrint('Uncaught: $error\n$stack');
    unawaited(crashes.report(error, stack, fatal: true));
    return true;
  };

  runApp(UncontrolledProviderScope(container: container, child: const GamyarApp()));
}
