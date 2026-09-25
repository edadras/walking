<?php

namespace App\Domain\Weather;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Open-Meteo forecast + air quality (no key for the free, non-commercial tier;
 * set WEATHER_API_KEY to use the commercial "customer-" hosts in production).
 */
class OpenMeteoProvider implements WeatherProvider
{
    private const CURRENT = 'temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m,wind_direction_10m,wind_gusts_10m,uv_index,precipitation,is_day';

    private const HOURLY = 'temperature_2m,weather_code,precipitation_probability,uv_index,is_day';

    private const DAILY = 'weather_code,temperature_2m_max,temperature_2m_min,sunrise,sunset,uv_index_max,precipitation_probability_max';

    public function __construct(private readonly ?string $apiKey, private readonly int $timeout = 6) {}

    public function fetch(float $lat, float $lng): array
    {
        $prefix = $this->apiKey ? 'customer-' : '';
        $key = $this->apiKey ? ['apikey' => $this->apiKey] : [];

        $forecast = Http::timeout($this->timeout)->retry(1, 200, throw: false)->get("https://{$prefix}api.open-meteo.com/v1/forecast", [
            'latitude' => $lat, 'longitude' => $lng, 'timezone' => 'auto',
            'current' => self::CURRENT, 'hourly' => self::HOURLY, 'daily' => self::DAILY,
            'forecast_days' => 3, 'forecast_hours' => 12, ...$key,
        ]);
        if (! $forecast->successful() || ! is_array($forecast->json('current'))) {
            throw new RuntimeException('weather upstream failed: '.$forecast->status());
        }

        // Air quality is a nice-to-have: its failure never hides the forecast.
        $air = null;
        try {
            $response = Http::timeout($this->timeout)->get("https://{$prefix}air-quality-api.open-meteo.com/v1/air-quality", [
                'latitude' => $lat, 'longitude' => $lng, 'timezone' => 'auto', 'current' => 'us_aqi,pm2_5,pm10', ...$key,
            ]);
            $air = $response->successful() ? $response->json('current') : null;
        } catch (\Throwable) {
            $air = null;
        }

        return ['forecast' => $forecast->json(), 'air' => is_array($air) ? $air : null];
    }
}
