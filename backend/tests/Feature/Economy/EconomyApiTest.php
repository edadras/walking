<?php

namespace Tests\Feature\Economy;

use App\Domain\Fraud\FraudCaseService;
use App\Domain\Wallet\WalletService;
use App\Enums\AdminRole;
use App\Enums\FraudCaseStatus;
use App\Enums\IntegrityVerdict;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\FraudCase;
use App\Models\PointTransaction;
use App\Models\Wallet;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWalkingSessions;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class EconomyApiTest extends TestCase
{
    use BuildsWalkingSessions, RefreshDatabase, SignsDeviceRequests;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 10:00:00', 'Asia/Tehran')->utc());
        $this->seed(PlatformSeeder::class);
    }

    private function realisticWalk(int $minutes, ?CarbonImmutable $end = null): array
    {
        $payload = $this->activeSession(minutes: $minutes, end: $end);
        foreach ($payload['buckets'] as $i => &$b) {
            $b['steps'] = 100 + ($i * 7) % 13;
            $b['detector_steps'] = $b['steps'] - 1;
        }
        $payload['raw_steps'] = array_sum(array_column($payload['buckets'], 'steps'));

        return $payload;
    }

    public function test_end_to_end_walk_is_scored_rewarded_and_released(): void
    {
        $this->loginAs();
        $this->device->forceFill(['integrity_verdict' => IntegrityVerdict::Device])->save();

        $walk = $this->realisticWalk(30);
        $this->signedJson('POST', '/api/v1/walking-sessions', $walk)->assertStatus(202);

        $expectedPoints = intdiv($walk['raw_steps'] * 10, 1000);
        $this->authedJson('GET', '/api/v1/home')
            ->assertJsonPath('data.today.verified_steps', $walk['raw_steps'])
            ->assertJsonPath('data.today.pending_steps', 0)
            ->assertJsonPath('data.today.points', $expectedPoints)
            ->assertJsonPath('data.wallet.pending', $expectedPoints)
            ->assertJsonPath('data.wallet.available', 0);

        $this->authedJson('GET', '/api/v1/walking-sessions')->assertJsonPath('data.0.status', 'verified')->assertJsonPath('data.0.reward_status', 'pending');

        $this->travel(25)->hours();
        $this->artisan('wallet:release-pending');

        $this->authedJson('GET', '/api/v1/wallet')
            ->assertOk()
            ->assertJsonPath('data.available', $expectedPoints)
            ->assertJsonPath('data.pending', 0)
            ->assertJsonPath('data.rial_value', $expectedPoints * 500);
    }

    public function test_wallet_history_filters(): void
    {
        $user = $this->loginAs();
        $wallet = app(WalletService::class);
        $wallet->credit($user, 100, TransactionType::SponsorReward, 'a', 'اسپانسر');
        $wallet->debit($user, 40, TransactionType::Purchase, 'b', 'خرید');
        $wallet->hold($user, 25, TransactionType::WalkingReward, 'c', 'قدم');

        $this->authedJson('GET', '/api/v1/wallet/transactions')->assertJsonCount(3, 'data')->assertJsonPath('data.0.type', 'walking_reward')->assertJsonPath('data.0.status_label', 'در حال بررسی');
        $this->authedJson('GET', '/api/v1/wallet/transactions?filter=spent')->assertJsonCount(1, 'data')->assertJsonPath('data.0.amount', -40);
        $this->authedJson('GET', '/api/v1/wallet/transactions?filter=sponsor')->assertJsonCount(1, 'data');
        $this->authedJson('GET', '/api/v1/wallet/transactions?filter=reward')->assertJsonCount(1, 'data');
        $this->authedJson('GET', '/api/v1/wallet/transactions?filter=earned')->assertJsonCount(2, 'data');
        $this->authedJson('GET', '/api/v1/wallet/transactions?filter=hack')->assertStatus(422);
    }

    public function test_reward_center_explains_how_to_earn(): void
    {
        $this->loginAs();

        $data = $this->authedJson('GET', '/api/v1/rewards')->assertOk()->json('data');

        $this->assertSame(1000, $data['earning']['steps_per_unit']);
        $this->assertSame(10, $data['earning']['points_per_unit']);
        $this->assertSame(300, $data['earning']['daily_cap']);
        $this->assertSame(20, $data['earning']['goal_bonus']);
        $this->assertSame('2026-09-25', $data['earning']['upcoming_multipliers'][0]['date']); // Friday ×1.5
        $this->assertSame(1.5, $data['earning']['upcoming_multipliers'][0]['multiplier']);
    }

    public function test_reward_breakdown_is_private(): void
    {
        $this->loginAs();
        $this->device->forceFill(['integrity_verdict' => IntegrityVerdict::Device])->save();
        $this->signedJson('POST', '/api/v1/walking-sessions', $this->realisticWalk(20))->assertStatus(202);
        $id = $this->authedJson('GET', '/api/v1/rewards')->json('data.recent.0.id');

        $this->authedJson('GET', "/api/v1/rewards/$id")->assertOk()->assertJsonPath('data.breakdown.rate.steps', 1000);

        $this->device = null;
        $this->deviceKey = $this->newDeviceKey();
        $this->travel(2)->minutes();
        $this->loginAs('09350000000');
        $this->authedJson('GET', "/api/v1/rewards/$id")->assertNotFound();
    }

    public function test_case_approval_rewards_and_rejection_reverses(): void
    {
        $user = $this->loginAs();
        $admin = Admin::factory()->role(AdminRole::FraudAnalyst)->create();
        $this->device->forceFill(['integrity_verdict' => IntegrityVerdict::Device])->save();

        // Metronome-perfect cadence → review.
        $walk = $this->activeSession(minutes: 15, perMinute: 120, end: CarbonImmutable::now()->subHours(2));
        $this->signedJson('POST', '/api/v1/walking-sessions', $walk)->assertStatus(202);
        $case = FraudCase::query()->sole();
        $this->assertSame(0, PointTransaction::query()->count());

        app(FraudCaseService::class)->approve($case, $admin, 'بررسی شد');
        $this->assertSame(FraudCaseStatus::Approved, $case->fresh()->status);
        $this->assertSame(18, Wallet::query()->find($user->id)->pending_balance);

        // A second suspicious walk, rejected: nothing is paid for it.
        $this->signedJson('POST', '/api/v1/walking-sessions', $this->activeSession(minutes: 15, perMinute: 120, end: CarbonImmutable::now()->subMinutes(10)))->assertStatus(202);
        $second = FraudCase::query()->where('status', 'open')->sole();
        app(FraudCaseService::class)->reject($second, $admin, 'دستگاه لرزاننده');
        $this->assertSame(18, Wallet::query()->find($user->id)->pending_balance);
    }

    public function test_ban_reverses_all_pending_points_and_blocks_the_account(): void
    {
        $user = $this->loginAs();
        $admin = Admin::factory()->role(AdminRole::FraudAnalyst)->create();
        $this->device->forceFill(['integrity_verdict' => IntegrityVerdict::Device])->save();

        $this->signedJson('POST', '/api/v1/walking-sessions', $this->realisticWalk(20, CarbonImmutable::now()->subHours(3)))->assertStatus(202);
        $this->signedJson('POST', '/api/v1/walking-sessions', $this->activeSession(minutes: 15, perMinute: 120, end: CarbonImmutable::now()->subMinutes(5)))->assertStatus(202);
        $this->assertGreaterThan(0, Wallet::query()->find($user->id)->pending_balance);

        app(FraudCaseService::class)->ban(FraudCase::query()->sole(), $admin, 'مزرعه گوشی');

        $this->assertSame(0, Wallet::query()->find($user->id)->pending_balance);
        $this->assertSame(0, PointTransaction::query()->where('status', TransactionStatus::Pending)->count());
        $this->assertSame(UserStatus::Banned, $user->fresh()->status);
        $this->app['auth']->forgetGuards();
        $this->authedJson('GET', '/api/v1/me')->assertUnauthorized();
    }
}
