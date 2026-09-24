<?php

namespace App\Domain\Activity;

use App\Domain\Settings\Settings;
use App\Enums\ActivityType;
use App\Models\UserProfile;

/**
 * Server-side distance and calorie estimates. The client never sends these.
 *
 * Step length (anthropometric rule of thumb):
 *   walking  = 0.414 × height
 *   running  = walking × 1.25
 *
 * Energy (ACSM metabolic equations, net of resting VO2, so the figure is the
 * extra energy spent moving — what fitness apps call "active calories"):
 *   walking VO2net = 0.1 × speed(m/min)  ml·kg⁻¹·min⁻¹
 *   running VO2net = 0.2 × speed(m/min)
 *   kcal = VO2net × weight(kg) × minutes / 1000 × 5   (≈5 kcal per litre O2)
 *
 * All values are estimates and labelled "تخمینی" in the UI.
 */
class ActivityEstimator
{
    /** Cadence (steps/min) from which a bucket is treated as running. */
    public const RUNNING_CADENCE = 145;

    /** Typical walking cadence used to estimate active time for coarse passive windows. */
    public const TYPICAL_CADENCE = 100;

    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  list<array{duration_s:int, steps:int, activity_type?:?string}>  $buckets
     * @param  array{distance_m?:int|float}|null  $gps
     * @return array{distance_m:int, active_duration_s:int, calories_kcal:float, activity_type:ActivityType}
     */
    public function estimate(array $buckets, ?UserProfile $profile, ?array $gps = null): array
    {
        $height = ($profile?->height_cm ?: $this->settings->int('activity.default_height_cm')) / 100;
        $weight = $profile?->weight_kg ?: (float) $this->settings->int('activity.default_weight_kg');
        $walkStep = 0.414 * $height;

        $distance = 0.0;
        $active = 0;
        $kcal = 0.0;
        $runningSteps = 0;
        $walkingSteps = 0;

        foreach ($buckets as $b) {
            $steps = (int) $b['steps'];
            if ($steps <= 0) {
                continue;
            }

            // Time actually spent moving inside the bucket (passive windows are mostly idle).
            $moving = min((int) $b['duration_s'], (int) round($steps / self::TYPICAL_CADENCE * 60));
            $moving = max($moving, 1);
            $cadence = $steps / ($moving / 60);
            $running = ($b['activity_type'] ?? null) === 'running' || $cadence >= self::RUNNING_CADENCE;

            $meters = $steps * $walkStep * ($running ? 1.25 : 1.0);
            $speed = $meters / ($moving / 60); // m/min
            $kcal += ($running ? 0.2 : 0.1) * $speed * $weight * ($moving / 60) / 1000 * 5;

            $distance += $meters;
            $active += $moving;
            $running ? $runningSteps += $steps : $walkingSteps += $steps;
        }

        // GPS distance wins when it is plausible against the step-based figure.
        $gpsDistance = (float) ($gps['distance_m'] ?? 0);
        if ($gpsDistance > 0 && $distance > 0 && $gpsDistance >= $distance * 0.5 && $gpsDistance <= $distance * 1.8) {
            $kcal *= $gpsDistance / $distance;
            $distance = $gpsDistance;
        }

        $type = match (true) {
            $runningSteps + $walkingSteps === 0 => ActivityType::Unknown,
            $runningSteps === 0 => ActivityType::Walking,
            $walkingSteps === 0 => ActivityType::Running,
            default => $runningSteps > $walkingSteps * 3 ? ActivityType::Running : ActivityType::Mixed,
        };

        return [
            'distance_m' => (int) round($distance),
            'active_duration_s' => $active,
            'calories_kcal' => round($kcal, 1),
            'activity_type' => $type,
        ];
    }
}
