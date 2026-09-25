<?php

namespace Tests\Feature\Cashout;

use App\Domain\Wallet\ConversionRate;
use App\Domain\Wallet\WalletService;
use App\Enums\AdminRole;
use App\Enums\TransactionType;
use App\Filament\Admin\Resources\Cashout\BankAccountResource;
use App\Filament\Admin\Resources\Cashout\CashoutRequestResource;
use App\Filament\Admin\Resources\Cashout\Pages\ListBankAccounts;
use App\Filament\Admin\Resources\Cashout\Pages\ListCashoutRequests;
use App\Filament\Admin\Resources\Cashout\Pages\ListUserIdentities;
use App\Filament\Admin\Resources\Cashout\Pages\ViewCashoutRequest;
use App\Models\Admin;
use App\Models\BankAccount;
use App\Models\CashoutRequest;
use App\Models\User;
use App\Models\UserIdentity;
use Database\Seeders\PlatformSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesCashoutData;
use Tests\TestCase;

class CashoutAdminTest extends TestCase
{
    use CreatesCashoutData, RefreshDatabase;

    private User $user;

    private BankAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app(ConversionRate::class)->set(10);
        $this->user = User::factory()->create();
        UserIdentity::query()->create(['user_id' => $this->user->id, 'first_name' => 'مریم', 'last_name' => 'احمدی',
            'national_code' => $this->nationalCode(), 'national_code_hash' => UserIdentity::hashNationalCode($this->nationalCode()),
            'birth_date' => '1995-03-21', 'status' => UserIdentity::PENDING, 'submitted_at' => now()]);
        $iban = $this->sheba();
        $this->account = BankAccount::query()->create(['user_id' => $this->user->id, 'iban' => $iban, 'iban_hash' => BankAccount::hashIban($iban),
            'iban_last4' => substr($iban, -4), 'bank_name' => 'ملی ایران', 'holder_name' => 'مریم احمدی', 'status' => BankAccount::PENDING]);
    }

    private function as(AdminRole $role): Admin
    {
        $admin = Admin::factory()->role($role)->create();
        // AuthenticateSession logs out a session whose user changed; start clean per admin.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function pendingRequest(int $points = 10000): CashoutRequest
    {
        app(WalletService::class)->credit($this->user, 50000, TransactionType::WalkingReward, 'seed-'.$points, 'x');
        $r = CashoutRequest::query()->create(['user_id' => $this->user->id, 'bank_account_id' => $this->account->id, 'points' => $points,
            'rial_per_point' => 10, 'amount_rial' => $points * 10, 'status' => CashoutRequest::PENDING, 'idempotency_key' => 'k'.$points]);
        app(WalletService::class)->debit($this->user, $points, TransactionType::Cashout, 'cashout:'.$r->public_id, 'x', $r);

        return $r;
    }

    public function test_support_reviews_identity_before_the_bank_account(): void
    {
        $this->as(AdminRole::Support);
        $this->get('/admin/cashout/identities')->assertOk()->assertSee('مریم')->assertDontSee($this->nationalCode());

        // The account can't be verified while the identity isn't.
        Livewire::test(ListBankAccounts::class)->assertTableActionHidden('verify', $this->account);

        Livewire::test(ListUserIdentities::class)->callTableAction('reveal', UserIdentity::query()->find($this->user->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'cashout.national_code_revealed']);
        Livewire::test(ListUserIdentities::class)->callTableAction('verify', UserIdentity::query()->find($this->user->id));
        $this->assertSame(UserIdentity::VERIFIED, UserIdentity::query()->find($this->user->id)->status);

        Livewire::test(ListBankAccounts::class)->callTableAction('verify', $this->account);
        $this->assertSame(BankAccount::VERIFIED, $this->account->fresh()->status);
        $this->assertSame(2, $this->user->notifications()->count());
    }

    public function test_rejection_reason_reaches_the_user(): void
    {
        $this->as(AdminRole::Support);
        Livewire::test(ListBankAccounts::class)->callTableAction('reject', $this->account, ['reason' => 'نام صاحب حساب متفاوت است']);
        $this->assertSame('نام صاحب حساب متفاوت است', $this->account->fresh()->rejection_reason);
    }

    public function test_finance_approves_and_another_admin_records_the_transfer(): void
    {
        $r = $this->pendingRequest();
        $a = $this->as(AdminRole::Finance);
        $this->get('/admin/cashout/requests')->assertOk()->assertSee('مریم احمدی');
        Livewire::test(ListCashoutRequests::class)->callTableAction('approve', $r);
        $r->refresh();
        $this->assertSame(CashoutRequest::APPROVED, $r->status);
        $this->assertSame($a->id, $r->approved_by);

        // The approver doesn't even get the button.
        Livewire::test(ViewCashoutRequest::class, ['record' => $r->getRouteKey()])->assertActionHidden('paid')->assertSee($this->sheba());

        // Export for the bank batch contains the full Sheba.
        $csv = CashoutRequestResource::exportApproved();
        ob_start();
        $csv->sendContent();
        $content = ob_get_clean();
        $this->assertStringContainsString($this->sheba(), $content);
        $this->assertStringContainsString('100000', $content);

        $this->as(AdminRole::Finance);
        Livewire::test(ViewCashoutRequest::class, ['record' => $r->getRouteKey()])->callAction('paid', ['bank_reference' => 'PAYA-123']);
        $this->assertSame(CashoutRequest::PAID, $r->fresh()->status);
        $this->assertSame('PAYA-123', $r->fresh()->bank_reference);
    }

    public function test_reject_refunds(): void
    {
        $r = $this->pendingRequest(20000);
        $this->as(AdminRole::Finance);
        Livewire::test(ListCashoutRequests::class)->callTableAction('reject', $r, ['reason' => 'مغایرت']);
        $this->assertSame(CashoutRequest::REJECTED, $r->fresh()->status);
        $this->assertSame(50000, $this->user->wallet()->first()->available_balance);
    }

    public function test_access_is_role_based(): void
    {
        $this->as(AdminRole::Support);
        $this->get('/admin/cashout/requests')->assertForbidden();
        $this->as(AdminRole::Finance);
        $this->get('/admin/cashout/identities')->assertForbidden();
        $this->as(AdminRole::ContentEditor);
        $this->get('/admin/cashout/bank-accounts')->assertForbidden();
        $this->assertFalse(BankAccountResource::canCreate());
    }
}
