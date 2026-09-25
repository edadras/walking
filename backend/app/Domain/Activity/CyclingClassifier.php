<?php

namespace App\Domain\Activity;

use App\Domain\Settings\Settings;
use App\Models\ActivitySample;
use App\Models\WalkingSession;
use Illuminate\Support\Collection;

/**
 * Finds the minutes of a session spent on a bicycle. Cycling is only measured with GPS
 * (there is no step count to go on), so a minute counts when:
 *
 *   - GPS speed is in the cycling band (default 10–40 km/h) with a usable fix, and
 *   - Activity Recognition said "bicycle", or the phone shows pedalling: few steps
 *     (cadence < 60/min) but a clearly moving body (accelerometer spread ≥ threshold).
 *     A phone in a car at the same speed is much smoother, which is what separates them.
 *   - Activity Recognition did not say "vehicle".
 *
 * A session needs a few such minutes, no mock location and no car-like top speed.
 * Thresholds are admin settings: they are starting values to be tuned on field data.
 */
class CyclingClassifier
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  Collection<int, ActivitySample>  $buckets
     * @return array{buckets: list<int>, distance_m: int, duration_s: int}
     */
    public function classify(WalkingSession $session, Collection $buckets): array
    {
        $none = ['buckets' => [], 'distance_m' => 0, 'duration_s' => 0];
        $gps = $session->gps_summary;
        if ($gps === null || ($gps['mock_detected'] ?? false) || ($session->motion_summary['mock_location'] ?? false)) {
            return $none;
        }
        if ((float) ($gps['max_speed_mps'] ?? 0) > $this->float('cycling.max_top_speed_kmh') / 3.6) {
            return $none;
        }

        $min = $this->float('cycling.min_speed_kmh') / 3.6;
        $max = $this->float('cycling.max_speed_kmh') / 3.6;
        $accuracy = $this->settings->int('cycling.max_gps_accuracy_m');
        $accel = $this->float('cycling.min_accel_std');

        $indexes = [];
        $distance = 0.0;
        $duration = 0;
        foreach ($buckets as $i => $b) {
            if ($b->speed_mps === null || $b->activity_type === 'vehicle') {
                continue;
            }
            $speed = (float) $b->speed_mps;
            if ($speed < $min || $speed > $max || ($b->gps_accuracy_m !== null && $b->gps_accuracy_m > $accuracy)) {
                continue;
            }
            $cadence = $b->duration_s > 0 ? $b->steps / ($b->duration_s / 60) : 0;
            $pedalling = $cadence < 60 && $b->accel_std !== null && (float) $b->accel_std >= $accel;
            if ($b->activity_type !== 'bicycle' && ! $pedalling) {
                continue;
            }
            $indexes[] = $i;
            $distance += $speed * $b->duration_s;
            $duration += $b->duration_s;
        }

        if ($duration < $this->settings->int('cycling.min_minutes') * 60) {
            return $none;
        }
        // Never more than the GPS track itself says was covered.
        if (isset($gps['distance_m'])) {
            $distance = min($distance, (float) $gps['distance_m']);
        }

        return ['buckets' => $indexes, 'distance_m' => (int) round($distance), 'duration_s' => $duration];
    }

    private function float(string $key): float
    {
        return (float) $this->settings->get($key);
    }
}
