import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/localization/l10n.dart';
import '../core/theme/app_theme.dart';
import '../features/notifications/application/push_service.dart';
import 'router.dart';
import 'session_effects.dart';

class GamyarApp extends ConsumerWidget {
  const GamyarApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // Push registration and notification-tap routing follow the session.
    ref.watch(pushControllerProvider);
    ref.watch(sessionEffectsProvider);
    return MaterialApp.router(
      onGenerateTitle: (c) => c.l10n.appName,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      // Dark theme is fully tokenised; enable by switching themeMode once designed/QA'd.
      darkTheme: AppTheme.dark,
      themeMode: ThemeMode.light,
      locale: const Locale('fa'),
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      builder: (context, child) => MediaQuery(
        // Respect the user's text size but cap it where layouts would break.
        data: MediaQuery.of(context).copyWith(textScaler: MediaQuery.textScalerOf(context).clamp(maxScaleFactor: 1.3)),
        child: child!,
      ),
      routerConfig: ref.watch(routerProvider),
    );
  }
}
