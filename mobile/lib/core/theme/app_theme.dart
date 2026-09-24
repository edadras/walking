import 'package:flutter/cupertino.dart' show CupertinoPageTransitionsBuilder;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'app_palette.dart';
import 'app_typography.dart';
import 'tokens.dart';

abstract final class AppTheme {
  static ThemeData get light => _build(AppPalette.light, Brightness.light);

  /// Wired through the whole design system; v1 ships light only.
  static ThemeData get dark => _build(AppPalette.dark, Brightness.dark);

  static ThemeData _build(AppPalette p, Brightness brightness) {
    final scheme = ColorScheme(
      brightness: brightness,
      primary: p.green,
      onPrimary: Colors.white,
      primaryContainer: p.greenSoft,
      onPrimaryContainer: p.greenStrong,
      secondary: p.gold,
      onSecondary: p.ink,
      secondaryContainer: p.goldSoft,
      onSecondaryContainer: p.goldInk,
      error: p.danger,
      onError: Colors.white,
      errorContainer: p.dangerSoft,
      onErrorContainer: p.danger,
      surface: p.bg,
      onSurface: p.ink,
      onSurfaceVariant: p.inkMuted,
      surfaceContainerLowest: p.bg,
      surfaceContainerLow: p.surface,
      surfaceContainer: p.surface,
      surfaceContainerHigh: p.surfaceSunken,
      surfaceContainerHighest: p.surfaceSunken,
      outline: p.border,
      outlineVariant: p.border,
    );
    final text = AppTypography.textTheme(p.ink, p.inkMuted);

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      scaffoldBackgroundColor: p.bg,
      fontFamily: AppTypography.family,
      textTheme: text,
      extensions: [p],
      splashFactory: InkSparkle.splashFactory,
      dividerTheme: DividerThemeData(color: p.border, thickness: 1, space: 1),
      appBarTheme: AppBarTheme(
        backgroundColor: p.bg,
        surfaceTintColor: Colors.transparent,
        foregroundColor: p.ink,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: text.titleMedium,
        systemOverlayStyle: brightness == Brightness.light ? SystemUiOverlayStyle.dark : SystemUiOverlayStyle.light,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: p.surface,
        contentPadding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.lg, vertical: 14),
        hintStyle: text.bodyMedium?.copyWith(color: p.inkSubtle),
        border: OutlineInputBorder(borderRadius: AppRadius.smAll, borderSide: BorderSide(color: p.border)),
        enabledBorder: OutlineInputBorder(borderRadius: AppRadius.smAll, borderSide: BorderSide(color: p.border)),
        focusedBorder: OutlineInputBorder(borderRadius: AppRadius.smAll, borderSide: BorderSide(color: p.green, width: 1.5)),
        errorBorder: OutlineInputBorder(borderRadius: AppRadius.smAll, borderSide: BorderSide(color: p.danger)),
        focusedErrorBorder: OutlineInputBorder(borderRadius: AppRadius.smAll, borderSide: BorderSide(color: p.danger, width: 1.5)),
        errorStyle: text.bodySmall?.copyWith(color: p.danger),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: p.bg,
        surfaceTintColor: Colors.transparent,
        shape: const RoundedRectangleBorder(borderRadius: AppRadius.lgTop),
        showDragHandle: true,
        dragHandleColor: p.border,
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: p.ink,
        contentTextStyle: text.bodyMedium?.copyWith(color: p.bg),
        shape: const RoundedRectangleBorder(borderRadius: AppRadius.smAll),
      ),
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? Colors.white : p.inkSubtle),
        trackColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? p.green : p.surfaceSunken),
        trackOutlineColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? p.green : p.border),
      ),
      listTileTheme: ListTileThemeData(
        contentPadding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.gutter),
        titleTextStyle: text.bodyLarge,
        subtitleTextStyle: text.bodySmall,
        iconColor: p.inkMuted,
        minVerticalPadding: AppSpacing.md,
      ),
      pageTransitionsTheme: const PageTransitionsTheme(builders: {
        TargetPlatform.android: FadeForwardsPageTransitionsBuilder(),
        TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
      }),
    );
  }
}
