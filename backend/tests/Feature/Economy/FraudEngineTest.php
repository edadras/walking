<?php

namespace Tests\Feature\Economy;

use App\Domain\Fraud\FraudEngine;
use App\Domain\Fraud\RuleRegistry;
use App\Enums\IntegrityVerdict;
use App\Enums\SessionStatus;
use App\Events\WalkingSessionScored;
use App\Models\Device;
use App\Models\FraudCase;
use App\Models\FraudEvent;
use App\Models\FraudRule;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesScoredSessions;
use Tests\TestCase;

class FraudEngineTest extends TestCase
{
    use CreatesScoredSessions, RefreshDatabase;

    private User $user;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        Event::fake([WalkingSessionScored::class]);
        $this->user = User::factory()->create();
        $this->device = Device::factory()->create(['integrity_verdict' => IntegrityVerdict::Device]);
        $this->device->users()->attach($this->user, ['first_seen_at' => now(), 'last_seen_at' => now()]);
    }

    private function score($session)
    {
        return app(FraudEngine::class)->score($session);
    }

    public function test_a_normal_walk_is_fully_verified(): void
    {
        $s = $this->score($this->makeSession($this->user, $this->device, $this->walkingMinutes(20)));

        $this->assertSame(SessionStatus::Verified, $s->status);
        $this->assertSame($s->raw_steps, $s->verified_steps);
        $this->assertLessThan(20, $s->fraud_score);
        $this->assertGreaterThan(80, $s->confidence_score);
        $this->assertSame(0, FraudEvent::query()->count());
        Event::assertDispatched(WalkingSessionScored::class);
    }

    public function test_impossible_minutes_are_dropped_and_high_cadence_capped(): void
    {
        $buckets = $this->walkingMinutes(10);
        $buckets[3]['steps'] = 300; // > 250/min: impossible → 0
        $buckets[4]['steps'] = 230; // > 220/min ceiling → capped to 180
        $buckets[3]['detector_steps'] = 298;
        $buckets[4]['detector_steps'] = 228;

        $s = $this->score($this->makeSession($this->user, $this->device, $buckets));

        $this->assertSame(SessionStatus::PartiallyVerified, $s->status);
        $this->assertSame($s->raw_steps - 300 - 50, $s->verified_steps);
        $this->assertTrue(FraudEvent::query()->where('rule_key', 'impossible_rate')->exists());
    }

    public function test_steps_in_a_vehicle_do_not_count(): void
    {
        $buckets = $this->walkingMinutes(9);
        foreach ([0, 1, 2, 3, 4, 5] as $i) {
            $buckets[$i]['activity_type'] = 'vehicle';
        }

        $s = $this->score($this->makeSession($this->user, $this->device, $buckets));

        $expected = array_sum(array_column(array_slice($buckets, 6), 'steps'));
        $this->assertSame($expected, $s->verified_steps);
        $this->assertGreaterThan(0, $s->fraud_score);
    }

    public function test_a_shaker_device_with_metronome_cadence_goes_to_review(): void
    {
        $buckets = array_fill(0, 15, ['steps' => 120, 'detector_steps' => 120, 'accel_std' => 2.0, 'accel_peak_hz' => 2.0]);

        $s = $this->score($this->makeSession($this->user, $this->device, $buckets));

        $this->assertSame(SessionStatus::UnderReview, $s->status);
        $case = FraudCase::query()->sole();
        $this->assertSame('open', $case->status->value);
        $this->assertStringContainsString('metronome_pattern', $case->reason);
    }

    public function test_mock_location_on_a_failed_integrity_device_is_rejected(): void
    {
        $this->device->forceFill(['integrity_verdict' => IntegrityVerdict::None])->save();

        $s = $this->score($this->makeSession($this->user, $this->device, $this->walkingMinutes(10), ['motion_summary' => ['mock_location' => true]]));

        $this->assertSame(SessionStatus::Rejected, $s->status);
        $this->assertSame(0, $s->verified_steps);
        $this->assertGreaterThanOrEqual(80, $s->fraud_score);
    }

    public function test_devices_without_integrity_are_capped_not_rejected(): void
    {
        $this->device->forceFill(['integrity_verdict' => IntegrityVerdict::Unavailable])->save();
        FraudRule::query()->where('key', 'device_integrity')->update(['params' => json_encode(['unavailable_daily_cap' => 1000])]);
        app(RuleRegistry::class)->flush();

        $s = $this->score($this->makeSession($this->user, $this->device, $this->walkingMinutes(20)));

        $this->assertSame(SessionStatus::PartiallyVerified, $s->status);
        $this->assertSame(1000, $s->verified_steps);
    }

    public function test_overlapping_time_on_another_device_is_not_counted_twice(): void
    {
        $s = $this->score($this->makeSession($this->user, $this->device, $this->walkingMinutes(10), ['overlap_s' => 600]));

        $this->assertSame(0, $s->verified_steps);
    }

    public function test_too_many_accounts_on_one_device_raises_risk(): void
    {
        foreach (User::factory()->count(3)->create() as $other) {
            $this->device->users()->attach($other, ['first_seen_at' => now(), 'last_seen_at' => now()]);
        }

        $s = $this->score($this->makeSession($this->user, $this->device, $this->walkingMinutes(10)));

        $this->assertSame(SessionStatus::UnderReview, $s->status);
        $this->assertTrue(FraudEvent::query()->where('rule_key', 'multi_account_device')->exists());
    }

    public function test_admins_can_disable_or_reweight_rules(): void
    {
        foreach (User::factory()->count(3)->create() as $other) {
            $this->device->users()->attach($other, ['first_seen_at' => now(), 'last_seen_at' => now()]);
        }
        FraudRule::query()->where('key', 'multi_account_device')->update(['is_enabled' => false]);
        app(RuleRegistry::class)->flush();

        $s = $this->score($this->makeSession($this->user, $this->device, $this->walkingMinutes(10)));

        $this->assertSame(SessionStatus::Verified, $s->status);
    }

    public function test_scoring_is_idempotent(): void
    {
        $s = $this->score($this->makeSession($this->user, $this->device, $this->walkingMinutes(5)));
        $scoredAt = $s->scored_at;

        $again = $this->score($s->fresh());

        $this->assertEquals($scoredAt, $again->scored_at);
        Event::assertDispatchedTimes(WalkingSessionScored::class, 1);
    }

    public function test_verified_steps_update_the_daily_row(): void
    {
        $s = $this->score($this->makeSession($this->user, $this->device, $this->walkingMinutes(10)));

        $this->assertSame($s->verified_steps, $this->user->dailyActivities()->value('verified_steps'));
    }
}
