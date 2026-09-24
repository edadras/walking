import 'package:flutter/material.dart';

/// Persian-first type scale on Vazirmatn. Line heights are generous because
/// Persian glyphs have tall ascenders/descenders.
abstract final class AppTypography {
  static const family = 'Vazirmatn';

  static TextTheme textTheme(Color ink, Color muted) => TextTheme(
        // Hero step number.
        displayLarge: TextStyle(fontFamily: family, fontSize: 44, height: 1.1, fontWeight: FontWeight.w800, color: ink, fontFeatures: const [FontFeature.tabularFigures()]),
        displaySmall: TextStyle(fontFamily: family, fontSize: 30, height: 1.2, fontWeight: FontWeight.w800, color: ink, fontFeatures: const [FontFeature.tabularFigures()]),
        headlineMedium: TextStyle(fontFamily: family, fontSize: 24, height: 1.4, fontWeight: FontWeight.w700, color: ink),
        headlineSmall: TextStyle(fontFamily: family, fontSize: 19, height: 1.45, fontWeight: FontWeight.w700, color: ink),
        titleMedium: TextStyle(fontFamily: family, fontSize: 16, height: 1.5, fontWeight: FontWeight.w600, color: ink),
        titleSmall: TextStyle(fontFamily: family, fontSize: 14.5, height: 1.5, fontWeight: FontWeight.w600, color: ink),
        bodyLarge: TextStyle(fontFamily: family, fontSize: 15, height: 1.75, fontWeight: FontWeight.w400, color: ink),
        bodyMedium: TextStyle(fontFamily: family, fontSize: 14, height: 1.75, fontWeight: FontWeight.w400, color: ink),
        bodySmall: TextStyle(fontFamily: family, fontSize: 12, height: 1.5, fontWeight: FontWeight.w400, color: muted),
        labelLarge: TextStyle(fontFamily: family, fontSize: 14, height: 1.4, fontWeight: FontWeight.w600, color: ink),
        labelMedium: TextStyle(fontFamily: family, fontSize: 13, height: 1.4, fontWeight: FontWeight.w500, color: muted),
        labelSmall: TextStyle(fontFamily: family, fontSize: 11, height: 1.3, fontWeight: FontWeight.w500, color: muted),
      );
}
