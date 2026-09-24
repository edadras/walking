import 'package:flutter/animation.dart';
import 'package:flutter/painting.dart';

/// Design tokens (docs/phase-0/08-design-system.md). Components read these
/// through [AppPalette] / Theme, never hard-coded values.
abstract final class AppColorsLight {
  static const bg = Color(0xFFFFFFFF);
  static const surface = Color(0xFFF6F8F6);
  static const surfaceSunken = Color(0xFFEEF2EF);
  static const border = Color(0xFFE3E8E4);
  static const ink = Color(0xFF101814);
  static const inkMuted = Color(0xFF56615B);
  static const inkSubtle = Color(0xFF8A948E);
  static const green = Color(0xFF1A7F4B);
  static const greenStrong = Color(0xFF0F5E36);
  static const greenSoft = Color(0xFFE7F3EC);
  static const gold = Color(0xFFE8A400);
  static const goldInk = Color(0xFF8A5D00);
  static const goldSoft = Color(0xFFFFF4D1);
  static const danger = Color(0xFFC3362B);
  static const dangerSoft = Color(0xFFFBECEA);
  static const info = Color(0xFF2563A8);
}

abstract final class AppColorsDark {
  static const bg = Color(0xFF0C110E);
  static const surface = Color(0xFF141B17);
  static const surfaceSunken = Color(0xFF1B241F);
  static const border = Color(0xFF26312B);
  static const ink = Color(0xFFEAF0EC);
  static const inkMuted = Color(0xFFA3AFA8);
  static const inkSubtle = Color(0xFF6F7A74);
  static const green = Color(0xFF3FB57A);
  static const greenStrong = Color(0xFF6CCB9A);
  static const greenSoft = Color(0xFF16291F);
  static const gold = Color(0xFFF2B92E);
  static const goldInk = Color(0xFFF2B92E);
  static const goldSoft = Color(0xFF2B2413);
  static const danger = Color(0xFFE5675C);
  static const dangerSoft = Color(0xFF2E1A18);
  static const info = Color(0xFF6BA3E0);
}

abstract final class AppSpacing {
  static const double xxs = 2;
  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 20;
  static const double xxl = 24;
  static const double x3 = 32;
  static const double x4 = 40;
  static const double x5 = 56;

  /// Horizontal page gutter.
  static const double gutter = 20;
}

abstract final class AppRadius {
  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;

  static const BorderRadius smAll = BorderRadius.all(Radius.circular(sm));
  static const BorderRadius mdAll = BorderRadius.all(Radius.circular(md));
  static const BorderRadius lgTop = BorderRadius.vertical(top: Radius.circular(lg));
}

abstract final class AppMotion {
  static const fast = Duration(milliseconds: 120);
  static const base = Duration(milliseconds: 200);
  static const slow = Duration(milliseconds: 320);
  static const count = Duration(milliseconds: 700);
  static const Curve enter = Curves.easeOutCubic;
  static const Curve exit = Curves.easeInCubic;
}

abstract final class AppSizes {
  static const double minTouchTarget = 48;
  static const double buttonHeight = 48;
  static const double iconMd = 22;
}
