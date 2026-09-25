<?php

namespace Tests\Feature\Economy;

use App\Domain\Activity\ActivityEstimator;
use App\Domain\Fraud\FraudEngine;
use App\Enums\ActivityType;
use App\Enums\IntegrityVerdict;
use App\Enums\SessionStatus;
use App\Enums\TransactionType;
use App\Models\DailyActivity;
use App\Models\Device;
use App\Models\PointTransaction;
use App\Models\Reward;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScoredSessions;
use Tests\TestCase;

class CyclingTest extends TestCase
{
    use CreatesScoredSessions, RefreshDatabase;

    private User $user;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 10:00:00', 'Asia/Tehran')->utc());
        $this->seed(PlatformSeeder::class);
        $this->user = User::factory()->create();
        $this->device = Device::factory()->create(['integrity_verdict' => IntegrityVerdict::Device]);
        $this->device->users()->attach($this->user, ['first_seen_at' => now(), 'last_seen_at' => now()]);
    }

    /** Minutes on a bike: a few stray steps, pedalling vibration, steady GPS speed. */
    private function rideMinutes(int $minutes, float $speed = 5.0, float $accel = 1.6, ?string $label = null): array
    {
        return array_map(fn ($i) => [
            'steps' => $i % 4,
            'detector_steps' => $i % 3,
            'accel_std' => $accel,
            'accel_peak_hz' => 1.3,
            'speed_mps' => $speed + ($i % 5) * 0.2,
            'gps_accuracy_m' => 8,
            'activity_type' => $label,
        ], range(0, $minutes - 1));
    }

    private function score(array $buckets, ?array $gps = null, ?CarbonImmutable $start = null)
    {
        $distance = array_sum(array_map(fn ($b) => ($b['speed_mps'] ?? 0) * 60, $buckets));
        $gps ??= ['points' => 400, 'distance_m' => $distance, 'avg_accuracy_m' => 8, 'max_speed_mps' => 7.5, 'jumps' => 0, 'mock_detected' => false];
        $session = $this->makeSession($this->user, $this->device, $buckets, ['gps_summary' => $gps], $start ?? CarbonImmutable::now()->subHours(3)->addMinutes($this->seq * 50));

        return app(FraudEngine::class)->score($session)->fresh();
    }

    public function test_a_gps_ride_is_recognised_and_paid_by_distance_at_a_lower_rate(): void
    {
        $s = $this->score($this->rideMinutes(20));

        $this->assertContains($s->status, [SessionStatus::Verified, SessionStatus::PartiallyVerified]);
        $this->assertSame(ActivityType::Bicycle, $s->activity_type);
        $this->assertSame(0, $s->verified_steps);
        $this->assertSame(1200, $s->cycling_duration_s);
        $this->assertEqualsWithDelta(6480, $s->cycling_distance_m, 1);
        $this->assertLessThan(50, $s->fraud_score);

        // 6.48 km × 3 = 19 points, as its own reward and ledger type.
        $reward = Reward::query()->where('kind', 'cycling')->sole();
        $this->assertSame(19, $reward->final_points);
        $tx = PointTransaction::query()->where('type', TransactionType::CyclingReward)->sole();
        $this->assertSame(19, $tx->amount);
        $daily = DailyActivity::query()->sole();
        $this->assertSame(19, $daily->cycling_points);
        $this->assertEqualsWithDelta(6480, $daily->cycling_distance_m, 1);
        $this->assertSame('pending', $s->reward_status->value);
    }

    public function test_a_kilometre_on_foot_is_worth_more_than_a_kilometre_on_a_bike(): void
    {
        $buckets = $this->walkingMinutes(12);
        $walk = $this->makeSession($this->user, $this->device, $buckets, start: CarbonImmutable::now()->subHours(5));
        app(FraudEngine::class)->score($walk);
        $walkPoints = Reward::query()->where('kind', 'walking')->sole()->final_points;
        $walkKm = app(ActivityEstimator::class)->estimate(array_map(fn ($b) => $b + ['duration_s' => 60], $buckets), $this->user->profile)['distance_m'] / 1000;
        $walkPerKm = $walkPoints / $walkKm;

        $this->score($this->rideMinutes(20));
        $ride = Reward::query()->where('kind', 'cycling')->sole();
        $ridePerKm = $ride->final_points / ($ride->breakdown['cycling_distance_m'] / 1000);

        $this->assertGreaterThan($ridePerKm * 3, $walkPerKm);
    }

    public function test_a_smooth_ride_at_bike_speed_is_a_car_and_earns_nothing(): void
    {
        $s = $this->score($this->rideMinutes(20, accel: 0.3));

        $this->assertSame(0, $s->cycling_distance_m);
        $this->assertNotSame(ActivityType::Bicycle, $s->activity_type);
        $this->assertGreaterThan(0, $s->fraud_score);
        $this->assertFalse(Reward::query()->where('kind', 'cycling')->exists());
    }

    public function test_activity_recognition_decides_when_it_speaks(): void
    {
        // Phone in a handlebar mount (little vibration) but Google says bicycle → a ride.
        $bike = $this->score($this->rideMinutes(10, accel: 0.4, label: 'bicycle'));
        $this->assertGreaterThan(2900, $bike->cycling_distance_m);

        // Vibrating like a bike but Google says vehicle → not a ride.
        $car = $this->score($this->rideMinutes(10, label: 'vehicle'));
        $this->assertSame(0, $car->cycling_distance_m);
    }

    public function test_no_gps_mock_location_or_car_top_speed_means_no_cycling(): void
    {
        $noGps = $this->makeSession($this->user, $this->device, $this->rideMinutes(10), start: CarbonImmutable::now()->subHours(6));
        $this->assertSame(0, app(FraudEngine::class)->score($noGps)->fresh()->cycling_distance_m);

        $mock = $this->score($this->rideMinutes(10), ['distance_m' => 3000, 'max_speed_mps' => 6, 'mock_detected' => true]);
        $this->assertSame(0, $mock->cycling_distance_m);

        $fast = $this->score($this->rideMinutes(10), ['distance_m' => 3000, 'max_speed_mps' => 22, 'mock_detected' => false]);
        $this->assertSame(0, $fast->cycling_distance_m);
    }

    public function test_short_hops_do_not_count_and_distance_never_exceeds_the_track(): void
    {
        $short = $this->score($this->rideMinutes(2));
        $this->assertSame(0, $short->cycling_distance_m);

        $track = $this->score($this->rideMinutes(10), ['distance_m' => 2000, 'max_speed_mps' => 7, 'mock_detected' => false]);
        $this->assertSame(2000, $track->cycling_distance_m);
    }

    public function test_walk_then_ride_counts_both(): void
    {
        $s = $this->score([...$this->walkingMinutes(10), ...$this->rideMinutes(10)]);

        $this->assertGreaterThan(900, $s->verified_steps);
        $this->assertGreaterThan(2900, $s->cycling_distance_m);
        $this->assertSame(ActivityType::Bicycle, $s->activity_type); // half the session
        $this->assertTrue(Reward::query()->where('kind', 'walking')->where('final_points', '>', 0)->exists());
        $this->assertTrue(Reward::query()->where('kind', 'cycling')->where('final_points', '>', 0)->exists());
    }

    public function test_cycling_has_its_own_daily_cap(): void
    {
        foreach (range(1, 3) as $i) {
            $this->score($this->rideMinutes(90, speed: 6), ['distance_m' => 40000, 'max_speed_mps' => 8, 'mock_detected' => false], CarbonImmutable::now()->subHours(9)->addHours($i * 2));
        }

        $this->assertSame(60, DailyActivity::query()->sole()->cycling_points);
        $this->assertSame(60, (int) Reward::query()->where('kind', 'cycling')->sum('final_points'));
    }
}
