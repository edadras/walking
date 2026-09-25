<?php

namespace App\Domain\Weather;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Weather for walkers: current conditions, 12 hours, 3 days, air quality and
 * plain-language advice (heat, UV, air, wind, rain, darkness).
 *
 * Privacy: coordinates are snapped to a ~5 km grid before they reach the provider
 * or the cache key and are never stored. One upstream call serves everyone in a cell
 * for 15 minutes; if the provider is down, the last good report (≤ 3 h) is served as stale.
 */
class WeatherService
{
    public const GRID = 0.05;

    private const FRESH_SECONDS = 900;

    private const STALE_SECONDS = 10800;

    public function __construct(private readonly WeatherProvider $provider) {}

    /** @return array<string, mixed>|null null when nothing (fresh or stale) is available */
    public function at(float $lat, float $lng): ?array
    {
        [$lat, $lng] = [self::snap($lat), self::snap($lng)];
        $key = sprintf('weather:v1:%.2f:%.2f', $lat, $lng);

        $fresh = Cache::get($key);
        if (is_array($fresh)) {
            return $fresh + ['stale' => false];
        }

        try {
            $raw = $this->provider->fetch($lat, $lng);
            $report = $this->normalize($raw['forecast'], $raw['air'], $lat, $lng);
            Cache::put($key, $report, self::FRESH_SECONDS);
            Cache::put($key.':last', $report, self::STALE_SECONDS);

            return $report + ['stale' => false];
        } catch (\Throwable $e) {
            Log::warning('weather.unavailable', ['error' => $e->getMessage()]);
            $last = Cache::get($key.':last');

            return is_array($last) ? $last + ['stale' => true] : null;
        }
    }

    public static function snap(float $v): float
    {
        return round(round($v / self::GRID) * self::GRID, 2);
    }

    /**
     * @param  array<string, mixed>  $f  Open-Meteo forecast response
     * @param  array<string, mixed>|null  $air  Open-Meteo air-quality "current" block
     * @return array<string, mixed>
     */
    public function normalize(array $f, ?array $air, float $lat, float $lng): array
    {
        $offset = (int) ($f['utc_offset_seconds'] ?? 0);
        $time = fn (?string $local) => $local === null ? null : self::localTime($local, $offset)->toIso8601String();
        $c = $f['current'];

        $current = [
            'temp_c' => round((float) $c['temperature_2m'], 1),
            'feels_like_c' => round((float) ($c['apparent_temperature'] ?? $c['temperature_2m']), 1),
            'humidity' => (int) round((float) ($c['relative_humidity_2m'] ?? 0)),
            'wind_kmh' => round((float) ($c['wind_speed_10m'] ?? 0), 1),
            'wind_gusts_kmh' => round((float) ($c['wind_gusts_10m'] ?? 0), 1),
            'wind_dir_deg' => (int) ($c['wind_direction_10m'] ?? 0),
            'wind_dir' => self::compass((float) ($c['wind_direction_10m'] ?? 0)),
            'uv' => round((float) ($c['uv_index'] ?? 0), 1),
            'uv_level' => self::uvLevel((float) ($c['uv_index'] ?? 0)),
            'precipitation_mm' => round((float) ($c['precipitation'] ?? 0), 1),
            'is_day' => (bool) ($c['is_day'] ?? true),
            ...self::condition((int) $c['weather_code'], (bool) ($c['is_day'] ?? true)),
        ];

        $hourly = [];
        $h = $f['hourly'] ?? [];
        foreach ($h['time'] ?? [] as $i => $t) {
            $hourly[] = [
                'time' => $time($t),
                'temp_c' => round((float) $h['temperature_2m'][$i], 1),
                'precip_prob' => (int) ($h['precipitation_probability'][$i] ?? 0),
                'uv' => round((float) ($h['uv_index'][$i] ?? 0), 1),
                ...self::condition((int) $h['weather_code'][$i], (bool) ($h['is_day'][$i] ?? true)),
            ];
        }

        $daily = [];
        $d = $f['daily'] ?? [];
        foreach ($d['time'] ?? [] as $i => $date) {
            $daily[] = [
                'date' => $date,
                'min_c' => round((float) $d['temperature_2m_min'][$i], 1),
                'max_c' => round((float) $d['temperature_2m_max'][$i], 1),
                'precip_prob' => (int) ($d['precipitation_probability_max'][$i] ?? 0),
                'uv_max' => round((float) ($d['uv_index_max'][$i] ?? 0), 1),
                'sunrise' => $time($d['sunrise'][$i] ?? null),
                'sunset' => $time($d['sunset'][$i] ?? null),
                ...self::condition((int) $d['weather_code'][$i], true),
            ];
        }

        $airQuality = $air === null || ! isset($air['us_aqi']) ? null : [
            'us_aqi' => (int) $air['us_aqi'],
            'pm2_5' => isset($air['pm2_5']) ? round((float) $air['pm2_5'], 1) : null,
            'pm10' => isset($air['pm10']) ? round((float) $air['pm10'], 1) : null,
            ...self::aqiLevel((int) $air['us_aqi']),
        ];

        $now = isset($c['time']) ? self::localTime($c['time'], $offset) : CarbonImmutable::now();
        $report = [
            'location' => ['lat' => $lat, 'lng' => $lng, 'elevation_m' => isset($f['elevation']) ? (int) round((float) $f['elevation']) : null],
            'observed_at' => $now->toIso8601String(),
            'current' => $current,
            'air' => $airQuality,
            'hourly' => $hourly,
            'daily' => $daily,
            'sun' => ['sunrise' => $daily[0]['sunrise'] ?? null, 'sunset' => $daily[0]['sunset'] ?? null],
        ];
        $advice = WalkAdvice::for($report, $now);

        return $report + ['advice' => $advice['items'], 'walk_index' => $advice['index']];
    }

    private static function localTime(string $local, int $offsetSeconds): CarbonImmutable
    {
        $sign = $offsetSeconds < 0 ? '-' : '+';
        $abs = abs($offsetSeconds);

        return CarbonImmutable::parse(sprintf('%s%s%02d:%02d', $local, $sign, intdiv($abs, 3600), intdiv($abs % 3600, 60)));
    }

    /** @return array{code: int, condition: string, icon: string} */
    public static function condition(int $code, bool $isDay): array
    {
        [$text, $icon] = match (true) {
            $code === 0 => ['صاف', 'clear'],
            $code === 1 => ['کمی ابری', 'mostly_clear'],
            $code === 2 => ['نیمه‌ابری', 'partly_cloudy'],
            $code === 3 => ['ابری', 'cloudy'],
            in_array($code, [45, 48], true) => ['مه', 'fog'],
            in_array($code, [51, 53, 55, 56, 57], true) => ['نم‌نم باران', 'drizzle'],
            in_array($code, [61, 63, 65, 66, 67, 80, 81, 82], true) => [$code >= 80 ? 'رگبار' : 'باران', 'rain'],
            in_array($code, [71, 73, 75, 77, 85, 86], true) => ['برف', 'snow'],
            in_array($code, [95, 96, 99], true) => [$code === 95 ? 'رعدوبرق' : 'رعدوبرق و تگرگ', 'thunder'],
            default => ['نامشخص', 'cloudy'],
        };
        if (! $isDay && in_array($icon, ['clear', 'mostly_clear', 'partly_cloudy'], true)) {
            $icon .= '_night';
        }

        return ['code' => $code, 'condition' => $text, 'icon' => $icon];
    }

    /** Where the wind blows from, in eight Persian compass points. */
    public static function compass(float $deg): string
    {
        $points = ['شمال', 'شمال‌شرق', 'شرق', 'جنوب‌شرق', 'جنوب', 'جنوب‌غرب', 'غرب', 'شمال‌غرب'];

        return $points[(int) round(fmod($deg + 360, 360) / 45) % 8];
    }

    public static function uvLevel(float $uv): string
    {
        return match (true) {
            $uv < 3 => 'low',
            $uv < 6 => 'moderate',
            $uv < 8 => 'high',
            $uv < 11 => 'very_high',
            default => 'extreme',
        };
    }

    /** @return array{level: string, label: string} US AQI bands */
    public static function aqiLevel(int $aqi): array
    {
        return match (true) {
            $aqi <= 50 => ['level' => 'good', 'label' => 'پاک'],
            $aqi <= 100 => ['level' => 'moderate', 'label' => 'قابل قبول'],
            $aqi <= 150 => ['level' => 'sensitive', 'label' => 'ناسالم برای گروه‌های حساس'],
            $aqi <= 200 => ['level' => 'unhealthy', 'label' => 'ناسالم'],
            $aqi <= 300 => ['level' => 'very_unhealthy', 'label' => 'بسیار ناسالم'],
            default => ['level' => 'hazardous', 'label' => 'خطرناک'],
        };
    }
}
