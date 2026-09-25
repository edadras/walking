<?php

namespace Tests\Feature\Economy;

use App\Domain\Wallet\WalletService;
use App\Enums\AdminRole;
use App\Enums\TransactionType;
use App\Filament\Admin\Resources\ReconciliationRuns\Pages\ListReconciliationRuns;
use App\Models\Admin;
use App\Models\CashoutRequest;
use App\Models\ReconciliationRun;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LedgerReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        Http::fake();
        config(['walk.ops.alert_webhook' => 'https://chat.example/hook']);
    }

    public function test_consistent_ledger_passes(): void
    {
        $u = User::factory()->create();
        $w = app(WalletService::class);
        $w->credit($u, 500, TransactionType::WalkingReward, 'a', 'x');
        $w->hold($u, 70, TransactionType::WalkingReward, 'b', 'x');
        $w->debit($u, 200, TransactionType::Purchase, 'c', 'x');
        $this->artisan('ledger:reconcile')->expectsOutputToContain('status=ok')->assertSuccessful();
        $this->assertSame(300, ReconciliationRun::query()->first()->totals['points_available']);
        Http::assertNothingSent();
    }

    public function test_tampered_balance_and_broken_payout_are_reported_and_alerted(): void
    {
        $finance = Admin::factory()->role(AdminRole::Finance)->create();
        $u = User::factory()->create();
        $w = app(WalletService::class);
        $w->credit($u, 500, TransactionType::WalkingReward, 'a', 'x');
        DB::table('wallets')->where('user_id', $u->id)->update(['available_balance' => 900]); // edited outside the ledger

        // A rejected payout whose refund never happened.
        $r = CashoutRequest::query()->create(['user_id' => $u->id, 'bank_account_id' => $this->account($u), 'points' => 100, 'rial_per_point' => 10,
            'amount_rial' => 1000, 'status' => CashoutRequest::REJECTED, 'idempotency_key' => 'k']);
        $tx = $w->debit($u, 100, TransactionType::Cashout, 'cashout:'.$r->public_id, 'x', $r);
        $r->forceFill(['debit_transaction_id' => $tx->id])->save();

        $this->artisan('ledger:reconcile')->assertFailed();
        $run = ReconciliationRun::query()->latest('id')->first();
        $this->assertSame('issues', $run->status);
        $this->assertEqualsCanonicalizing(['available_mismatch', 'cashout_refund_missing'], array_column($run->issues, 'kind'));

        Http::assertSent(fn ($req) => $req->url() === 'https://chat.example/hook' && str_contains($req['text'], 'مغایرت'));
        $this->assertSame(1, $finance->notifications()->count(), 'finance sees it in the panel bell');

        // Same alert isn't repeated within the quiet period.
        $this->artisan('ledger:reconcile');
        Http::assertSentCount(1);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($finance, 'admin');
        $this->get('/admin/reconciliation-runs')->assertOk()->assertSee('مغایرت');
        Livewire::test(ListReconciliationRuns::class)->callAction('run');
        $this->assertSame(3, ReconciliationRun::query()->count());
    }

    private function account(User $u): int
    {
        return DB::table('bank_accounts')->insertGetId(['public_id' => (string) str()->ulid(), 'user_id' => $u->id, 'iban' => '', 'iban_hash' => hash('sha256', (string) $u->id),
            'iban_last4' => '0000', 'bank_name' => 'ملت', 'holder_name' => 'x', 'status' => 'verified', 'created_at' => now(), 'updated_at' => now()]);
    }
}
