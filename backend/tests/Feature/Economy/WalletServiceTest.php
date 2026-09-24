<?php

namespace Tests\Feature\Economy;

use App\Domain\Wallet\InsufficientPoints;
use App\Domain\Wallet\WalletService;
use App\Enums\AdminRole;
use App\Enums\FraudCaseStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\FraudCase;
use App\Models\PointTransaction;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $wallet;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        $this->wallet = app(WalletService::class);
        $this->user = User::factory()->create();
    }

    private function balance(): Wallet
    {
        return Wallet::query()->findOrFail($this->user->id);
    }

    public function test_hold_then_release_moves_points_to_available_with_balance_snapshots(): void
    {
        $t = $this->wallet->hold($this->user, 40, TransactionType::WalkingReward, 'k1', 'test');
        $this->assertSame([0, 40], [$this->balance()->available_balance, $this->balance()->pending_balance]);

        $this->wallet->release($t);

        $t->refresh();
        $this->assertSame(TransactionStatus::Completed, $t->status);
        $this->assertSame([0, 40], [$t->balance_before, $t->balance_after]);
        $this->assertSame([40, 0, 40], [$this->balance()->available_balance, $this->balance()->pending_balance, $this->balance()->lifetime_earned]);
    }

    public function test_reverse_cancels_a_hold_and_release_after_reverse_is_a_no_op(): void
    {
        $t = $this->wallet->hold($this->user, 40, TransactionType::WalkingReward, 'k1', 'test');
        $this->wallet->reverse($t, 'fraud');
        $this->wallet->release($t);

        $this->assertSame(TransactionStatus::Reversed, $t->fresh()->status);
        $this->assertSame([0, 0], [$this->balance()->available_balance, $this->balance()->pending_balance]);
    }

    public function test_same_idempotency_key_never_applies_twice(): void
    {
        $a = $this->wallet->credit($this->user, 100, TransactionType::SponsorReward, 'visit:9', 'x');
        $b = $this->wallet->credit($this->user, 100, TransactionType::SponsorReward, 'visit:9', 'x');

        $this->assertTrue($a->is($b));
        $this->assertSame(100, $this->balance()->available_balance);
    }

    public function test_debit_can_never_overdraw(): void
    {
        $this->wallet->credit($this->user, 50, TransactionType::SponsorReward, 'c', 'x');

        try {
            $this->wallet->debit($this->user, 51, TransactionType::Purchase, 'order:1', 'x');
            $this->fail('Expected InsufficientPoints');
        } catch (InsufficientPoints $e) {
            $this->assertSame(50, $e->available);
        }
        $this->assertSame(50, $this->balance()->available_balance);
        $this->assertSame(1, PointTransaction::query()->count());
    }

    public function test_pending_points_are_not_spendable(): void
    {
        $this->wallet->hold($this->user, 500, TransactionType::WalkingReward, 'h', 'x');

        $this->expectException(InsufficientPoints::class);
        $this->wallet->debit($this->user, 1, TransactionType::Purchase, 'order:1', 'x');
    }

    public function test_admin_adjustment_requires_a_reason_and_is_audited(): void
    {
        $admin = Admin::factory()->role(AdminRole::Finance)->create();

        $t = $this->wallet->adjust($this->user, 75, 'جبران خطای همگام‌سازی', $admin);

        $this->assertSame('admin', $t->performed_by_type);
        $this->assertSame($admin->id, $t->performed_by_id);
        $this->assertSame([0, 75], [$t->balance_before, $t->balance_after]);
        $this->assertSame(75, AuditLog::query()->where('action', 'wallet.adjusted')->sole()->meta['delta']);

        $this->expectException(\InvalidArgumentException::class);
        $this->wallet->adjust($this->user, 10, '  ', $admin);
    }

    public function test_ledger_rows_are_immutable(): void
    {
        $t = $this->wallet->credit($this->user, 10, TransactionType::SponsorReward, 'c', 'x');

        $this->expectException(LogicException::class);
        $t->update(['amount' => 1000]);
    }

    public function test_ledger_rows_cannot_be_deleted(): void
    {
        $t = $this->wallet->credit($this->user, 10, TransactionType::SponsorReward, 'c', 'x');

        $this->expectException(LogicException::class);
        $t->delete();
    }

    public function test_wallet_balance_always_equals_the_ledger(): void
    {
        $this->wallet->credit($this->user, 100, TransactionType::SponsorReward, 'a', 'x');
        $this->wallet->debit($this->user, 30, TransactionType::Purchase, 'b', 'x');
        $h = $this->wallet->hold($this->user, 20, TransactionType::WalkingReward, 'c', 'x');
        $this->wallet->release($h);
        $this->wallet->hold($this->user, 5, TransactionType::WalkingReward, 'd', 'x');

        $ledgerAvailable = (int) PointTransaction::query()->where('user_id', $this->user->id)->where('status', TransactionStatus::Completed)->sum('amount');
        $ledgerPending = (int) PointTransaction::query()->where('user_id', $this->user->id)->where('status', TransactionStatus::Pending)->sum('amount');
        $this->assertSame($ledgerAvailable, $this->balance()->available_balance);
        $this->assertSame($ledgerPending, $this->balance()->pending_balance);
    }

    public function test_release_command_skips_users_under_review_or_banned(): void
    {
        $ok = User::factory()->create();
        $reviewed = User::factory()->create();
        $banned = User::factory()->status(UserStatus::Banned)->create();
        foreach ([$ok, $reviewed, $banned] as $u) {
            $this->wallet->hold($u, 10, TransactionType::WalkingReward, 'h', 'x', availableAt: now()->subMinute());
        }
        $this->wallet->hold($ok, 10, TransactionType::WalkingReward, 'future', 'x', availableAt: now()->addHour());
        FraudCase::query()->create(['user_id' => $reviewed->id, 'risk_score' => 60, 'status' => FraudCaseStatus::Open, 'reason' => 'x']);

        $this->artisan('wallet:release-pending')->assertSuccessful();

        $this->assertSame(10, Wallet::query()->find($ok->id)->available_balance);
        $this->assertSame(10, Wallet::query()->find($ok->id)->pending_balance);
        $this->assertSame(0, Wallet::query()->find($reviewed->id)->available_balance);
        $this->assertSame(0, Wallet::query()->find($banned->id)->available_balance);
    }
}
