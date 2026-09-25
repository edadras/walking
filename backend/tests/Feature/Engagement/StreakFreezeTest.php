<?php

namespace Tests\Feature\Engagement;

use App\Domain\Fraud\FraudEngine;
use App\Domain\Gamification\StreakService;
use App\Domain\Wallet\WalletService;
use App\Enums\IntegrityVerdict;
use App\Enums\TransactionType;
use App\Models\Device;
use App\Models\StreakFreeze;
use App\Models\User;
use App\Models\UserStreak;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesScoredSessions;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class StreakFreezeTest extends TestCase
{
    use CreatesScoredSessions, RefreshDatabase, SignsDeviceRequests;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 21:00:00', 'Asia/Tehran')->utc());
        $this->seed(PlatformSeeder::class);
    }

    private function walker(): array
    {
        $user = User::factory()->create();
        $user->profile->update(['daily_step_goal' => 5000]);
        $device = Device::factory()->create(['integrity_verdict' => IntegrityVerdict::Device]);
        $device->users()->attach($user, ['first_seen_at' => now(), 'last_seen_at' => now()]);

        return [$user, $device];
    }

    private function walkOn(User $user, Device $device, string $date, int $steps = 5200): void
    {
        $buckets = [];
        for ($left = $steps, $i = 0; $left > 0; $i++) {
            $n = min($left, 100 + ($i * 7) % 11);
            $buckets[] = ['steps' => $n, 'detector_steps' => $n, 'accel_std' => 2.1, 'accel_peak_hz' => 1.9];
            $left -= $n;
        }
        $start = CarbonImmutable::parse("$date 10:00:00", 'Asia/Tehran')->utc()->addMinutes($this->seq * 90);
        app(FraudEngine::class)->score($this->makeSession($user, $device, $buckets, start: $start));
    }

    public function test_an_owned_freeze_covers_yesterday_without_adding_to_the_count(): void
    {
        [$user, $device] = $this->walker();
        StreakFreeze::query()->create(['user_id' => $user->id]);
        foreach (['2026-09-20', '2026-09-21', '2026-09-22', '2026-09-24'] as $d) {
            $this->walkOn($user, $device, $d);
        }

        $this->assertSame(4, UserStreak::query()->find($user->id)->current_days, '20,21,22 + 24; the 23rd is frozen');
        $this->assertSame('2026-09-23', StreakFreeze::query()->sole()->covered_date->toDateString());
        $this->assertTrue($user->notifications()->where('data->title', 'زنجیره‌ات حفظ شد')->exists());

        $week = collect(app(StreakService::class)->weekDots($user))->keyBy('date');
        $this->assertTrue($week['2026-09-23']['frozen']);
        $this->assertFalse($week['2026-09-23']['reached']);
    }

    public function test_without_a_freeze_the_gap_breaks_the_chain(): void
    {
        [$user, $device] = $this->walker();
        foreach (['2026-09-20', '2026-09-21', '2026-09-22', '2026-09-24'] as $d) {
            $this->walkOn($user, $device, $d);
        }
        $this->assertSame(1, UserStreak::query()->find($user->id)->current_days);
    }

    public function test_old_gaps_are_never_repaired_after_the_fact(): void
    {
        [$user, $device] = $this->walker();
        foreach (['2026-09-18', '2026-09-19', '2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24'] as $d) {
            $this->walkOn($user, $device, $d);
        }
        StreakFreeze::query()->create(['user_id' => $user->id]);
        app(StreakService::class)->refresh($user);

        $this->assertSame(4, UserStreak::query()->find($user->id)->current_days);
        $this->assertNull(StreakFreeze::query()->sole()->used_at, 'the gap on the 20th is too old');
    }

    public function test_buying_is_ledger_backed_idempotent_and_capped(): void
    {
        $user = $this->loginAs();
        app(WalletService::class)->credit($user, 1000, TransactionType::Adjustment, 'fund:'.Str::uuid(), 'test');
        $buy = fn (string $key) => $this->signedJson('POST', '/api/v1/streak/freezes', [], ['Idempotency-Key' => $key]);

        $key = (string) Str::uuid();
        $buy($key)->assertCreated()->assertJsonPath('data.freezes.owned', 1)->assertJsonPath('data.freezes.price', 300);
        $buy($key)->assertCreated()->assertJsonPath('data.freezes.owned', 1);
        $buy((string) Str::uuid())->assertCreated()->assertJsonPath('data.freezes.owned', 2)->assertJsonPath('data.freezes.can_buy', false);
        $buy((string) Str::uuid())->assertStatus(409)->assertJsonPath('error.code', 'freeze_limit');

        $this->assertSame(400, $user->wallet->fresh()->available_balance);
        $this->authedJson('GET', '/api/v1/progress')->assertJsonPath('data.streak.freezes.owned', 2);
    }

    public function test_buying_without_enough_points_changes_nothing(): void
    {
        $user = $this->loginAs();
        $this->signedJson('POST', '/api/v1/streak/freezes', [], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);
        $this->assertSame(0, StreakFreeze::query()->where('user_id', $user->id)->count());
    }
}
