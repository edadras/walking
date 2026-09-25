<?php

namespace App\Domain\Weather;

/**
 * Raw forecast + air quality for a point. Implementations return the normalized
 * shape documented on WeatherService::normalize(); they throw on upstream failure.
 */
interface WeatherProvider
{
    /**
     * @return array{forecast: array<string, mixed>, air: array<string, mixed>|null}
     */
    public function fetch(float $lat, float $lng): array;
}
