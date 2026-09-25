<?php

namespace Tests\Feature\Cashout;

use App\Domain\Cashout\CashoutRisk;
use App\Domain\Cashout\CashoutService;
use App\Domain\Cashout\WithdrawableBalance;
use App\Domain\Settings\Settings;
use App\Domain\Wallet\WalletService;
use App\Enums\AdminRole;
use App\Enums\TransactionType;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\BankAccount;
use App\Models\CashoutRequest;
use App\Models\Device;
use App\Models\User;
use App\Models\UserIdentity;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashoutEconomicsTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $w;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        $this->w = app(WalletService::class);
    }

    private function withdrawable(User $u): array
    {
        return app(WithdrawableBalance::class)->of($u);
    }

    public function test_only_matured_eligible_points_are_withdrawable(): void
    {
        $u = User::factory()->create();
        $this->travel(-20)->days();
        $this->w->credit($u, 10000, TransactionType::WalkingReward, 'old-walk', 'x');
        $this->w->credit($u, 5000, TransactionType::ReferralReward, 'old-ref', 'x'); // never withdrawable
        $this->travelBack();
        $this->w->credit($u, 3000, TransactionType::WalkingReward, 'new-walk', 'x'); // not matured yet

        $this->assertSame(['withdrawable' => 10000, 'available' => 18000, 'immature' => 3000], $this->withdrawable($u));

        // Spending comes out of the withdrawable part first (conservative) …
        $this->w->debit($u, 4000, TransactionType::Purchase, 'buy', 'x');
        $this->assertSame(6000, $this->withdrawable($u)['withdrawable']);
        // … and a refund restores it.
        $this->w->credit($u, 4000, TransactionType::Refund, 'refund', 'x');
        $this->assertSame(10000, $this->withdrawable($u)['withdrawable']);

        // Once the recent walk matures it counts too.
        $this->travel(15)->days();
        $this->assertSame(13000, $this->withdrawable($u)['withdrawable']);
    }

    public function test_withdrawable_never_exceeds_available(): void
    {
        $u = User::factory()->create();
        $this->travel(-20)->days();
        $this->w->credit($u, 10000, TransactionType::WalkingReward, 'a', 'x');
        $this->travelBack();
        $this->w->debit($u, 9000, TransactionType::Purchase, 'b', 'x');
        $this->assertSame(1000, $this->withdrawable($u)['withdrawable']);
    }

    public function test_risk_signals_for_a_device_farm(): void
    {
        $u = User::factory()->create(['created_at' => now()->subDays(10)]);
        $other = User::factory()->create();
        $device = Device::factory()->create(['emulator_suspected' => true]);
        foreach ([$u, $other] as $x) {
            DB::table('device_user_links')->insert(['device_id' => $device->id, 'user_id' => $x->id, 'first_seen_at' => now(), 'last_seen_at' => now()]);
        }
        UserIdentity::query()->create(['user_id' => $other->id, 'first_name' => 'ا', 'last_name' => 'ب', 'national_code' => '0499370899',
            'national_code_hash' => UserIdentity::hashNationalCode('0499370899'), 'birth_date' => '1990-01-01', 'status' => 'verified', 'submitted_at' => now()]);
        foreach (range(1, 7) as $d) {
            DB::table('daily_activities')->insert(['user_id' => $u->id, 'local_date' => now()->subDays($d)->toDateString(), 'verified_steps' => 25000, 'goal_steps' => 7500,
                'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (range(10, 30) as $d) {
            DB::table('daily_activities')->insert(['user_id' => $u->id, 'local_date' => now()->subDays($d)->toDateString(), 'verified_steps' => 4000, 'goal_steps' => 7500,
                'created_at' => now(), 'updated_at' => now()]);
        }

        $risk = app(CashoutRisk::class)->assess($u);
        $this->assertEqualsCanonicalizing(['young_account', 'shared_device', 'shared_device_payout', 'weak_device', 'step_spike'], array_column($risk['signals'], 'code'));
        $this->assertSame(90, $risk['score']);
        $this->assertSame('high', CashoutRisk::level($risk['score']));

        $clean = app(CashoutRisk::class)->assess(User::factory()->create(['created_at' => now()->subYear()]));
        $this->assertSame(0, $clean['score']);
    }

    public function test_budget_caps_approvals_and_keeps_the_rest_queued(): void
    {
        app(Settings::class)->set('cashout.daily_budget_rial', 150000);
        $u = User::factory()->create();
        $acc = BankAccount::query()->create(['user_id' => $u->id, 'iban' => 'IR1', 'iban_hash' => 'h1', 'iban_last4' => '0001', 'bank_name' => 'ملت', 'holder_name' => 'x', 'status' => 'verified']);
        $make = function (int $rial, string $key) use ($u, $acc) {
            $this->w->credit($u, $rial / 10, TransactionType::WalkingReward, 'c'.$key, 'x');
            $r = CashoutRequest::query()->create(['user_id' => $u->id, 'bank_account_id' => $acc->id, 'points' => $rial / 10, 'rial_per_point' => 10,
                'amount_rial' => $rial, 'status' => 'pending', 'idempotency_key' => $key]);
            $this->w->debit($u, $rial / 10, TransactionType::Cashout, 'cashout:'.$r->public_id, 'x', $r);

            return $r;
        };
        $a = $make(100000, 'a');
        $b = $make(100000, 'b');
        $admin = Admin::factory()->role(AdminRole::Finance)->create();
        $svc = app(CashoutService::class);
        $svc->approve($a, $admin);
        try {
            $svc->approve($b, $admin);
            $this->fail('over budget');
        } catch (ApiException $e) {
            $this->assertSame('budget_exceeded', $e->errorCode);
        }
        $this->assertSame(CashoutRequest::PENDING, $b->fresh()->status);
        $this->assertSame(['limit' => 150000, 'used' => 100000], $svc->budget()['daily']);

        // Next day there is room again; queue position is reported to the user.
        $this->assertSame(1, collect($svc->overview($u)['requests'])->firstWhere('id', $b->public_id)['queue_position']);
        $this->travel(1)->days();
        $svc->approve($b->fresh(), $admin);
        $this->assertSame(CashoutRequest::APPROVED, $b->fresh()->status);
    }
}
