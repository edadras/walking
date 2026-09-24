<?php

namespace Tests\Unit;

use App\Domain\Activity\ActivityEstimator;
use App\Domain\Settings\Settings;
use App\Enums\ActivityType;
use App\Models\UserProfile;
use Tests\TestCase;

class ActivityEstimatorTest extends TestCase
{
    private function estimator(): ActivityEstimator
    {
        return new ActivityEstimator(app(Settings::class));
    }

    public function test_walking_distance_and_calories_follow_the_documented_model(): void
    {
        $profile = new UserProfile(['height_cm' => 170, 'weight_kg' => 70]);
        // 10 minutes at 100 steps/min.
        $buckets = array_fill(0, 10, ['duration_s' => 60, 'steps' => 100]);

        $r = $this->estimator()->estimate($buckets, $profile);

        // 1000 steps × 0.414 × 1.70 m = 703.8 m
        $this->assertSame(704, $r['distance_m']);
        $this->assertSame(600, $r['active_duration_s']);
        // VO2net = 0.1 × 70.38 m/min = 7.04 ml/kg/min → 7.04 × 70 × 10 / 1000 × 5 = 24.6 kcal
        $this->assertEqualsWithDelta(24.6, $r['calories_kcal'], 0.2);
        $this->assertSame(ActivityType::Walking, $r['activity_type']);
    }

    public function test_high_cadence_is_treated_as_running(): void
    {
        $r = $this->estimator()->estimate(array_fill(0, 5, ['duration_s' => 60, 'steps' => 165]), new UserProfile(['height_cm' => 170, 'weight_kg' => 70]));

        $this->assertSame(ActivityType::Running, $r['activity_type']);
        $this->assertSame((int) round(825 * 0.414 * 1.7 * 1.25), $r['distance_m']);
    }

    public function test_passive_windows_count_only_estimated_moving_time(): void
    {
        $r = $this->estimator()->estimate([['duration_s' => 3600, 'steps' => 500]], null);

        $this->assertSame(300, $r['active_duration_s']); // 500 steps at ~100/min
    }

    public function test_plausible_gps_distance_overrides_step_estimate(): void
    {
        $buckets = array_fill(0, 10, ['duration_s' => 60, 'steps' => 100]);
        $profile = new UserProfile(['height_cm' => 170, 'weight_kg' => 70]);

        $this->assertSame(800, $this->estimator()->estimate($buckets, $profile, ['distance_m' => 800])['distance_m']);
        // Implausible GPS (e.g. driving) is ignored.
        $this->assertSame(704, $this->estimator()->estimate($buckets, $profile, ['distance_m' => 5000])['distance_m']);
    }
}
