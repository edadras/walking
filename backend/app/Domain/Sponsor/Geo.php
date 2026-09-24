<?php

namespace App\Domain\Sponsor;

final class Geo
{
    private const EARTH_RADIUS_M = 6_371_000;

    public static function distanceM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1, sqrt($a)));
    }

    /**
     * Bounding box for an index-friendly prefilter (exact distance is checked afterwards).
     *
     * @return array{0: float, 1: float, 2: float, 3: float} [minLat, maxLat, minLng, maxLng]
     */
    public static function box(float $lat, float $lng, float $radiusM): array
    {
        $dLat = rad2deg($radiusM / self::EARTH_RADIUS_M);
        $dLng = rad2deg($radiusM / (self::EARTH_RADIUS_M * max(0.01, cos(deg2rad($lat)))));

        return [$lat - $dLat, $lat + $dLat, $lng - $dLng, $lng + $dLng];
    }
}
