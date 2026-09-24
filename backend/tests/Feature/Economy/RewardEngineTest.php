<?php

namespace Tests\Feature\Economy;

use App\Domain\Fraud\FraudEngine;
use App\Domain\Reward\RewardEngine;
use App\Domain\Reward\RewardRules;
use App\Enums\IntegrityVerdict;
use App\Enums\TransactionStatus;
use App\Models\DailyActivity;
use App\Models\Device;
use App\Models\PointTransaction;
use App\Models\Reward;
use App\Models\RewardRule;
use App\Models\User;
use App\Models\Wallet;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScoredSessions;
use Tests\TestCase;

class RewardEngineTest extends TestCase
{
    use CreatesScoredSessions, RefreshDatabase;

    private User $user;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        // Thursday 2026-09-24 10:00 Tehran (no Friday multiplier).
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 10:00:00', 'Asia/Tehran')->utc());
        $this->seed(PlatformSeeder::class);
        $this->user = User::factory()->create();
        $this->user->profile->update(['daily_step_goal' => 10000]);
        $this->device = Device::factory()->create(['integrity_verdict' => IntegrityVerdict::Device]);
        $this->device->users()->attach($this->user, ['first_seen_at' => now(), 'last_seen_at' => now()]);
    }

    /** Submits-and-scores a walk with exactly $steps steps (100/min minutes). */
    private function walk(int $steps, ?CarbonImmutable $start = null)
    {
        $buckets = [];
        $left = $steps;
        $i = 0;
        while ($left > 0) {
            $n = min($left, 100 + ($i++ % 9));
            $buckets[] = ['steps' => $n, 'detector_steps' => $n, 'accel_std' => 2.1, 'accel_peak_hz' => 1.9];
            $left -= $n;
        }
        $session = $this->makeSession($this->user, $this->device, $buckets, start: $start ?? CarbonImmutable::now()->subHours(3)->addMinutes($this->seq * 40));

        // The listener chain (score → reward) runs synchronously in tests.
        return app(FraudEngine::class)->score($session)->fresh();
    }

    private function wallet(): Wallet
    {
        return Wallet::query()->findOrFail($this->user->id);
    }

    public function test_verified_steps_become_pending_points_not_spendable_ones(): void
    {
        $this->walk(3000);

        $this->assertSame(30, $this->wallet()->pending_balance);
        $this->assertSame(0, $this->wallet()->available_balance);
        $t = PointTransaction::query()->sole();
        $this->assertSame(TransactionStatus::Pending, $t->status);
        $this->assertSame(500, $t->rial_rate);
        $this->assertTrue($t->available_at->gt(now()->addHours(23)));
    }

    public function test_points_are_computed_on_the_daily_total_so_rounding_never_loses_points(): void
    {
        $this->walk(550);
        $this->walk(550);

        // 550 → 5 pts, then total 1100 → 11 pts: the second walk earns 6, not 5.
        $this->assertSame([5, 6], Reward::query()->orderBy('id')->pluck('final_points')->all());
    }

    public function test_daily_rewarded_steps_and_points_are_capped(): void
    {
        RewardRule::query()->where('rule_type', 'max_rewarded_steps')->update(['cap' => 5000]);
        app(RewardRules::class)->flush();

        $this->walk(4000);
        $this->walk(4000);

        $daily = DailyActivity::query()->sole();
        $this->assertSame(5000, $daily->rewarded_steps);
        $this->assertSame(50, (int) Reward::query()->where('kind', 'walking')->sum('final_points'));
    }

    public function test_friday_multiplier_applies(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-25 18:00:00', 'Asia/Tehran')->utc()); // Friday

        $this->walk(2000, CarbonImmutable::parse('2026-09-25 09:00:00', 'Asia/Tehran')->utc());

        $reward = Reward::query()->where('kind', 'walking')->sole();
        $this->assertSame(20, $reward->base_points);
        $this->assertSame(1.5, $reward->multiplier);
        $this->assertSame(30, $reward->final_points);
        $this->assertSame('جمعه‌ها ×۱٫۵', $reward->breakdown['multipliers'][0]['name']);
    }

    public function test_goal_bonus_is_granted_once(): void
    {
        $this->walk(6000);
        $this->assertSame(0, Reward::query()->where('kind', 'goal_bonus')->count());

        $this->walk(4500);
        $this->walk(1000);

        $this->assertSame(1, Reward::query()->where('kind', 'goal_bonus')->count());
        $this->assertSame(20, (int) Reward::query()->where('kind', 'goal_bonus')->value('final_points'));
    }

    public function test_a_session_is_rewarded_only_once(): void
    {
        $session = $this->walk(2000);

        app(RewardEngine::class)->forSession($session);
        app(RewardEngine::class)->forSession($session);

        $this->assertSame(1, Reward::query()->where('kind', 'walking')->count());
        $this->assertSame(1, PointTransaction::query()->count());
        $this->assertSame(20, $this->wallet()->pending_balance);
    }

    public function test_rejected_or_review_sessions_earn_nothing(): void
    {
        $this->device->forceFill(['integrity_verdict' => IntegrityVerdict::None])->save();
        $session = $this->makeSession($this->user, $this->device, $this->walkingMinutes(10), ['motion_summary' => ['mock_location' => true]]);
        app(FraudEngine::class)->score($session);

        $this->assertSame(0, PointTransaction::query()->count());
    }
}
