import 'dart:convert';
import 'dart:typed_data';

import 'package:crypto/crypto.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gamyar/core/localization/l10n.dart';
import 'package:gamyar/core/security/device_key.dart';
import 'package:gamyar/core/theme/app_theme.dart';
import 'package:go_router/go_router.dart';

const _delegates = [
  AppLocalizations.delegate,
  GlobalMaterialLocalizations.delegate,
  GlobalWidgetsLocalizations.delegate,
  GlobalCupertinoLocalizations.delegate,
];

/// Wraps a widget with theme, Persian locale (RTL) and a ProviderScope.
Widget testApp(Widget child, {List overrides = const []}) => ProviderScope(
      overrides: [...overrides],
      child: MaterialApp(
        theme: AppTheme.light,
        locale: const Locale('fa'),
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: _delegates,
        home: child,
      ),
    );

/// Same as [testApp] but driven by a GoRouter.
Widget testRouterApp(GoRouter router, {List overrides = const []}) => ProviderScope(
      overrides: [...overrides],
      child: MaterialApp.router(
        theme: AppTheme.light,
        locale: const Locale('fa'),
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: _delegates,
        routerConfig: router,
      ),
    );

/// Deterministic stand-in for the Keystore key: "signs" with HMAC so tests can
/// verify exactly which bytes were signed.
class FakeDeviceKey implements DeviceKey {
  final signed = <String>[];

  static String expectedSignature(String canonical) =>
      base64Encode(Hmac(sha256, utf8.encode('test-key')).convert(utf8.encode(canonical)).bytes);

  @override
  Future<String> publicKey() async => base64Encode(List.filled(91, 1));

  @override
  Future<String> sign(Uint8List data) async {
    final canonical = utf8.decode(data);
    signed.add(canonical);
    return expectedSignature(canonical);
  }

  @override
  Future<DeviceSignals> signals() async => const DeviceSignals(emulator: false, rooted: false, hardwareBacked: true);

  @override
  Future<String?> integrityToken(String requestHash, int cloudProjectNumber) async => null;
}
