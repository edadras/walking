import 'package:flutter/material.dart';

import 'tokens.dart';

/// Semantic colours beyond Material's ColorScheme (gold accent, subtle ink,
/// sunken surfaces). Read with `context.palette`.
@immutable
class AppPalette extends ThemeExtension<AppPalette> {
  const AppPalette({
    required this.bg,
    required this.surface,
    required this.surfaceSunken,
    required this.border,
    required this.ink,
    required this.inkMuted,
    required this.inkSubtle,
    required this.green,
    required this.greenStrong,
    required this.greenSoft,
    required this.gold,
    required this.goldInk,
    required this.goldSoft,
    required this.danger,
    required this.dangerSoft,
    required this.info,
  });

  static const light = AppPalette(
    bg: AppColorsLight.bg,
    surface: AppColorsLight.surface,
    surfaceSunken: AppColorsLight.surfaceSunken,
    border: AppColorsLight.border,
    ink: AppColorsLight.ink,
    inkMuted: AppColorsLight.inkMuted,
    inkSubtle: AppColorsLight.inkSubtle,
    green: AppColorsLight.green,
    greenStrong: AppColorsLight.greenStrong,
    greenSoft: AppColorsLight.greenSoft,
    gold: AppColorsLight.gold,
    goldInk: AppColorsLight.goldInk,
    goldSoft: AppColorsLight.goldSoft,
    danger: AppColorsLight.danger,
    dangerSoft: AppColorsLight.dangerSoft,
    info: AppColorsLight.info,
  );

  static const dark = AppPalette(
    bg: AppColorsDark.bg,
    surface: AppColorsDark.surface,
    surfaceSunken: AppColorsDark.surfaceSunken,
    border: AppColorsDark.border,
    ink: AppColorsDark.ink,
    inkMuted: AppColorsDark.inkMuted,
    inkSubtle: AppColorsDark.inkSubtle,
    green: AppColorsDark.green,
    greenStrong: AppColorsDark.greenStrong,
    greenSoft: AppColorsDark.greenSoft,
    gold: AppColorsDark.gold,
    goldInk: AppColorsDark.goldInk,
    goldSoft: AppColorsDark.goldSoft,
    danger: AppColorsDark.danger,
    dangerSoft: AppColorsDark.dangerSoft,
    info: AppColorsDark.info,
  );

  final Color bg;
  final Color surface;
  final Color surfaceSunken;
  final Color border;
  final Color ink;
  final Color inkMuted;
  final Color inkSubtle;
  final Color green;
  final Color greenStrong;
  final Color greenSoft;
  final Color gold;
  final Color goldInk;
  final Color goldSoft;
  final Color danger;
  final Color dangerSoft;
  final Color info;

  @override
  AppPalette copyWith() => this;

  @override
  AppPalette lerp(ThemeExtension<AppPalette>? other, double t) {
    if (other is! AppPalette) return this;
    Color l(Color a, Color b) => Color.lerp(a, b, t)!;
    return AppPalette(
      bg: l(bg, other.bg),
      surface: l(surface, other.surface),
      surfaceSunken: l(surfaceSunken, other.surfaceSunken),
      border: l(border, other.border),
      ink: l(ink, other.ink),
      inkMuted: l(inkMuted, other.inkMuted),
      inkSubtle: l(inkSubtle, other.inkSubtle),
      green: l(green, other.green),
      greenStrong: l(greenStrong, other.greenStrong),
      greenSoft: l(greenSoft, other.greenSoft),
      gold: l(gold, other.gold),
      goldInk: l(goldInk, other.goldInk),
      goldSoft: l(goldSoft, other.goldSoft),
      danger: l(danger, other.danger),
      dangerSoft: l(dangerSoft, other.dangerSoft),
      info: l(info, other.info),
    );
  }
}

extension AppThemeContext on BuildContext {
  AppPalette get palette => Theme.of(this).extension<AppPalette>()!;
  TextTheme get text => Theme.of(this).textTheme;
}
