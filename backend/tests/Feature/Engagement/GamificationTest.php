<?php

namespace Tests\Feature\Engagement;

use App\Domain\Fraud\FraudEngine;
use App\Enums\IntegrityVerdict;
use App\Models\Device;
use App\Models\PersonalRecord;
use App\Models\PointTransaction;
use App\Models\Reward;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserStreak;
use App\Models\XpTransaction;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScoredSessions;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use CreatesScoredSessions, RefreshDatabase;

    private User $user;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 21:00:00', 'Asia/Tehran')->utc());
        $this->seed(PlatformSeeder::class);
        $this->user = User::factory()->create();
        $this->user->profile->update(['daily_step_goal' => 5000]);
        $this->device = Device::factory()->create(['integrity_verdict' => IntegrityVerdict::Device]);
        $this->device->users()->attach($this->user, ['first_seen_at' => now(), 'last_seen_at' => now()]);
    }

    /** One verified walk of ~$steps on a given Tehran day (10:00). */
    private function walkOn(string $date, int $steps): void
    {
        $buckets = [];
        for ($left = $steps, $i = 0; $left > 0; $i++) {
            $n = min($left, 100 + ($i * 7) % 11);
            $buckets[] = ['steps' => $n, 'detector_steps' => $n, 'accel_std' => 2.1, 'accel_peak_hz' => 1.9];
            $left -= $n;
        }
        $start = CarbonImmutable::parse("$date 10:00:00", 'Asia/Tehran')->utc()->addMinutes($this->seq * 90);
        app(FraudEngine::class)->score($this->makeSession($this->user, $this->device, $buckets, start: $start));
    }

    public function test_verified_steps_give_xp_once_and_level_up(): void
    {
        $this->walkOn('2026-09-24', 6000);

        $user = $this->user->fresh();
        // 60 (6000 steps / 100) + 50 (goal) + 50 (badge "first 5k day"); level 2 needs 200.
        $this->assertSame(160, $user->xp);
        $this->assertSame(1, $user->level);

        $this->walkOn('2026-09-24', 5000); // +50 steps XP, +100 for the new 10k-day badge; goal XP already paid
        $this->assertSame(310, $this->user->fresh()->xp);
        $this->assertSame(2, $this->user->fresh()->level);
    }

    public function test_streak_counts_consecutive_goal_days_and_breaks_on_gaps(): void
    {
        foreach (['2026-09-18', '2026-09-20', '2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24'] as $d) {
            $this->walkOn($d, 5200);
        }

        $streak = UserStreak::query()->find($this->user->id);
        $this->assertSame(5, $streak->current_days);
        $this->assertSame(5, $streak->longest_days);
        $this->assertSame('2026-09-24', $streak->last_goal_date->toDateString());
        $this->assertSame(1, UserAchievement::query()->whereHas('achievement', fn ($q) => $q->where('key', 'streak_3'))->count());
    }

    public function test_streak_survives_until_the_end_of_today(): void
    {
        foreach (['2026-09-21', '2026-09-22', '2026-09-23'] as $d) {
            $this->walkOn($d, 5200);
        }
        $this->walkOn('2026-09-24', 1000); // today, goal not reached yet

        $this->assertSame(3, UserStreak::query()->find($this->user->id)->current_days);
    }

    public function test_seven_day_streak_pays_the_bonus_once(): void
    {
        foreach (range(18, 24) as $day) {
            $this->walkOn("2026-09-$day", 5200);
        }
        $this->walkOn('2026-09-24', 500); // another session same day must not pay again

        $bonus = Reward::query()->where('kind', 'streak_bonus')->get();
        $this->assertCount(1, $bonus);
        $this->assertSame(30, $bonus->first()->final_points);
    }

    public function test_achievements_unlock_once_with_xp(): void
    {
        $this->walkOn('2026-09-24', 10500);
        $this->walkOn('2026-09-24', 300);

        $keys = UserAchievement::query()->with('achievement')->get()->pluck('achievement.key')->sort()->values()->all();
        $this->assertSame(['first_10k_day', 'first_5k_day'], $keys);
        $this->assertSame(1, XpTransaction::query()->where('idempotency_key', 'achievement:'.UserAchievement::query()->first()->achievement_id)->count());
        // The 10k badge also pays 20 points (held like any reward).
        $this->assertSame(20, (int) PointTransaction::query()->where('type', 'achievement_reward')->sum('amount'));
    }

    public function test_personal_records(): void
    {
        $this->walkOn('2026-09-22', 7000);
        $this->walkOn('2026-09-24', 9000);

        $this->assertSame(9000, PersonalRecord::query()->where('metric', 'best_day_steps')->value('value'));
        $this->assertSame(16000, PersonalRecord::query()->where('metric', 'best_week_steps')->value('value'));
    }
}
