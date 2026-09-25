import 'package:flutter/material.dart';

import '../../../core/localization/l10n.dart';

/// Icons and sky colours for the server's condition keys.
abstract final class WeatherVisuals {
  static IconData icon(String key) => switch (key) {
        'clear' => Icons.wb_sunny_rounded,
        'clear_night' => Icons.nightlight_round,
        'mostly_clear' => Icons.wb_sunny_outlined,
        'mostly_clear_night' || 'partly_cloudy_night' => Icons.nights_stay_rounded,
        'partly_cloudy' => Icons.wb_cloudy_rounded,
        'fog' => Icons.foggy,
        'drizzle' => Icons.grain_rounded,
        'rain' => Icons.umbrella_rounded,
        'snow' => Icons.ac_unit_rounded,
        'thunder' => Icons.thunderstorm_rounded,
        _ => Icons.cloud_rounded,
      };

  /// Sky gradient (top → bottom) that reads well under white text.
  static List<Color> sky(String key, bool isDay) {
    if (!isDay) return const [Color(0xFF1F2B4D), Color(0xFF0E1630)];
    return switch (key) {
      'clear' || 'mostly_clear' => const [Color(0xFF3D8FD6), Color(0xFF1F5FA8)],
      'partly_cloudy' => const [Color(0xFF5A8FC0), Color(0xFF365F8C)],
      'fog' => const [Color(0xFF8A96A3), Color(0xFF5E6975)],
      'drizzle' || 'rain' => const [Color(0xFF4F6A85), Color(0xFF2E4258)],
      'snow' => const [Color(0xFF7F9DBA), Color(0xFF52708E)],
      'thunder' => const [Color(0xFF44405E), Color(0xFF262338)],
      _ => const [Color(0xFF6D8298), Color(0xFF475A6E)],
    };
  }

  static Color levelColor(String level) => switch (level) {
        'danger' => const Color(0xFFE5484D),
        'warn' => const Color(0xFFF5A524),
        'info' => const Color(0xFF3E9BE0),
        _ => const Color(0xFF30A46C),
      };

  static Color aqiColor(String level) => switch (level) {
        'good' => const Color(0xFF30A46C),
        'moderate' => const Color(0xFFE0B429),
        'sensitive' => const Color(0xFFF08C2E),
        'unhealthy' => const Color(0xFFE5484D),
        'very_unhealthy' => const Color(0xFF8E4EC6),
        _ => const Color(0xFF7A2E3B),
      };

  static Color uvColor(String level) => switch (level) {
        'low' => const Color(0xFF30A46C),
        'moderate' => const Color(0xFFE0B429),
        'high' => const Color(0xFFF08C2E),
        'very_high' => const Color(0xFFE5484D),
        _ => const Color(0xFF8E4EC6),
      };

  static String uvLabel(BuildContext context, String level) {
    final l = context.l10n;
    return switch (level) {
      'low' => l.weatherUvLow,
      'moderate' => l.weatherUvModerate,
      'high' => l.weatherUvHigh,
      'very_high' => l.weatherUvVeryHigh,
      _ => l.weatherUvExtreme,
    };
  }
}
