import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';

double _d(Object? v) => (v as num?)?.toDouble() ?? 0;
int _i(Object? v) => (v as num?)?.toInt() ?? 0;
DateTime? _t(Object? v) => v == null ? null : DateTime.parse(v as String);

class WeatherNow {
  const WeatherNow({
    required this.tempC,
    required this.feelsLikeC,
    required this.humidity,
    required this.windKmh,
    required this.gustsKmh,
    required this.windDirDeg,
    required this.windDir,
    required this.uv,
    required this.uvLevel,
    required this.isDay,
    required this.condition,
    required this.icon,
  });

  factory WeatherNow.fromJson(Map<String, dynamic> j) => WeatherNow(
        tempC: _d(j['temp_c']),
        feelsLikeC: _d(j['feels_like_c']),
        humidity: _i(j['humidity']),
        windKmh: _d(j['wind_kmh']),
        gustsKmh: _d(j['wind_gusts_kmh']),
        windDirDeg: _i(j['wind_dir_deg']),
        windDir: j['wind_dir'] as String? ?? '',
        uv: _d(j['uv']),
        uvLevel: j['uv_level'] as String? ?? 'low',
        isDay: j['is_day'] as bool? ?? true,
        condition: j['condition'] as String? ?? '',
        icon: j['icon'] as String? ?? 'cloudy',
      );

  final double tempC;
  final double feelsLikeC;
  final int humidity;
  final double windKmh;
  final double gustsKmh;
  final int windDirDeg;
  final String windDir;
  final double uv;
  final String uvLevel;
  final bool isDay;
  final String condition;
  final String icon;
}

class AirQuality {
  const AirQuality({required this.aqi, required this.level, required this.label, this.pm25, this.pm10});

  factory AirQuality.fromJson(Map<String, dynamic> j) => AirQuality(
        aqi: _i(j['us_aqi']),
        level: j['level'] as String? ?? 'good',
        label: j['label'] as String? ?? '',
        pm25: (j['pm2_5'] as num?)?.toDouble(),
        pm10: (j['pm10'] as num?)?.toDouble(),
      );

  final int aqi;
  final String level;
  final String label;
  final double? pm25;
  final double? pm10;
}

class HourForecast {
  const HourForecast({required this.time, required this.tempC, required this.precipProb, required this.uv, required this.icon});

  factory HourForecast.fromJson(Map<String, dynamic> j) =>
      HourForecast(time: _t(j['time'])!, tempC: _d(j['temp_c']), precipProb: _i(j['precip_prob']), uv: _d(j['uv']), icon: j['icon'] as String? ?? 'cloudy');

  final DateTime time;
  final double tempC;
  final int precipProb;
  final double uv;
  final String icon;
}

class DayForecast {
  const DayForecast({required this.date, required this.minC, required this.maxC, required this.precipProb, required this.uvMax, required this.condition, required this.icon, this.sunrise, this.sunset});

  factory DayForecast.fromJson(Map<String, dynamic> j) => DayForecast(
        date: DateTime.parse(j['date'] as String),
        minC: _d(j['min_c']),
        maxC: _d(j['max_c']),
        precipProb: _i(j['precip_prob']),
        uvMax: _d(j['uv_max']),
        condition: j['condition'] as String? ?? '',
        icon: j['icon'] as String? ?? 'cloudy',
        sunrise: _t(j['sunrise']),
        sunset: _t(j['sunset']),
      );

  final DateTime date;
  final double minC;
  final double maxC;
  final int precipProb;
  final double uvMax;
  final String condition;
  final String icon;
  final DateTime? sunrise;
  final DateTime? sunset;
}

class WalkAdviceItem {
  const WalkAdviceItem({required this.key, required this.level, required this.text});

  factory WalkAdviceItem.fromJson(Map<String, dynamic> j) =>
      WalkAdviceItem(key: j['key'] as String, level: j['level'] as String, text: j['text'] as String);

  final String key;
  final String level; // good | info | warn | danger
  final String text;
}

class WeatherReport {
  const WeatherReport({
    required this.now,
    required this.hourly,
    required this.daily,
    required this.advice,
    required this.walkScore,
    required this.walkLabel,
    required this.observedAt,
    required this.stale,
    this.air,
    this.elevationM,
    this.sunrise,
    this.sunset,
  });

  factory WeatherReport.fromJson(Map<String, dynamic> j) {
    final sun = j['sun'] as Map<String, dynamic>? ?? const {};
    final index = j['walk_index'] as Map<String, dynamic>? ?? const {};
    return WeatherReport(
      now: WeatherNow.fromJson(j['current'] as Map<String, dynamic>),
      air: j['air'] == null ? null : AirQuality.fromJson(j['air'] as Map<String, dynamic>),
      hourly: (j['hourly'] as List? ?? const []).map((e) => HourForecast.fromJson(e as Map<String, dynamic>)).toList(),
      daily: (j['daily'] as List? ?? const []).map((e) => DayForecast.fromJson(e as Map<String, dynamic>)).toList(),
      advice: (j['advice'] as List? ?? const []).map((e) => WalkAdviceItem.fromJson(e as Map<String, dynamic>)).toList(),
      walkScore: _i(index['score']),
      walkLabel: index['label'] as String? ?? '',
      observedAt: _t(j['observed_at']) ?? DateTime.now(),
      stale: j['stale'] as bool? ?? false,
      elevationM: ((j['location'] as Map<String, dynamic>?)?['elevation_m'] as num?)?.toInt(),
      sunrise: _t(sun['sunrise']),
      sunset: _t(sun['sunset']),
    );
  }

  final WeatherNow now;
  final AirQuality? air;
  final List<HourForecast> hourly;
  final List<DayForecast> daily;
  final List<WalkAdviceItem> advice;
  final int walkScore;
  final String walkLabel;
  final DateTime observedAt;
  final bool stale;
  final int? elevationM;
  final DateTime? sunrise;
  final DateTime? sunset;
}

class WeatherRepository {
  WeatherRepository(this._api);

  final ApiClient _api;

  /// Coordinates go out with 2 decimals (~1 km); the server snaps them further and never stores them.
  Future<WeatherReport> at(double lat, double lng) async {
    final r = await _api.get('/weather', query: {'lat': lat.toStringAsFixed(2), 'lng': lng.toStringAsFixed(2)});
    return WeatherReport.fromJson(r['data'] as Map<String, dynamic>);
  }
}

final weatherRepositoryProvider = Provider<WeatherRepository>((ref) => WeatherRepository(ref.watch(apiClientProvider)));
