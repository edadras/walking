<?php

namespace Tests\Feature\Cashout;

use App\Domain\Cashout\CashoutService;
use App\Domain\Cashout\KycChecks;
use App\Domain\Cashout\Providers\JibitClient;
use App\Domain\Cashout\Providers\KycProvider;
use App\Domain\Cashout\Providers\PayoutProvider;
use App\Domain\Settings\Settings;
use App\Domain\Wallet\WalletService;
use App\Enums\AdminRole;
use App\Enums\TransactionType;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\BankAccount;
use App\Models\CashoutRequest;
use App\Models\User;
use App\Models\UserIdentity;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesCashoutData;
use Tests\TestCase;

class JibitProvidersTest extends TestCase
{
    use CreatesCashoutData, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        config(['walk.cashout.kyc_driver' => 'jibit', 'walk.cashout.payout_driver' => 'jibit', 'walk.cashout.jibit' => [
            'base_url' => 'https://napi.jibit.ir', 'ide_api_key' => 'ide-k', 'ide_secret_key' => 'ide-s', 'cobank_api_key' => 'co-k', 'cobank_secret_key' => 'co-s',
            'source_iban' => null, 'transfer_type' => 'NORMAL',
        ]]);
        foreach ([JibitClient::class, KycProvider::class, PayoutProvider::class] as $a) {
            $this->app->forgetInstance($a);
        }
        Cache::flush();
        $this->user = User::factory()->create(['phone' => '+989121234567']);
    }

    private function identity(): UserIdentity
    {
        return UserIdentity::query()->create(['user_id' => $this->user->id, 'first_name' => 'مریم', 'last_name' => 'احمدی', 'national_code' => $this->nationalCode(),
            'national_code_hash' => UserIdentity::hashNationalCode($this->nationalCode()), 'birth_date' => '1995-03-21', 'status' => 'pending', 'submitted_at' => now()]);
    }

    private function fakeIde(bool $mobile = true, int $similarity = 100, bool $iban = true, string $ibanStatus = 'ACTIVE'): void
    {
        Http::fake([
            'napi.jibit.ir/ide/v1/tokens/generate' => Http::response(['accessToken' => 'ide-token', 'refreshToken' => 'r']),
            'napi.jibit.ir/ide/v1/services/matching*' => fn (Request $r) => Http::response(['matched' => isset($r->data()['mobileNumber']) ? $mobile : $iban]),
            'napi.jibit.ir/ide/v1/services/identity/similarity*' => Http::response(['firstNameSimilarityPercentage' => $similarity, 'lastNameSimilarityPercentage' => 100]),
            'napi.jibit.ir/ide/v1/ibans*' => Http::response(['value' => 'x', 'ibanInfo' => ['bank' => 'MELLI', 'status' => $ibanStatus, 'owners' => [['firstName' => 'مریم', 'lastName' => 'احمدی']]]]),
        ]);
    }

    public function test_identity_inquiry_uses_shahkar_and_registry_with_jalali_birth_date(): void
    {
        $this->fakeIde();
        app(Settings::class)->set('cashout.kyc_auto_approve', true);
        $identity = $this->identity();

        $this->assertSame('passed', app(KycChecks::class)->identity($identity));
        $this->assertSame(UserIdentity::VERIFIED, $identity->fresh()->status, 'auto-approved');
        $this->assertNull($identity->fresh()->reviewed_by);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://napi.jibit.ir/ide/v1/tokens/generate' && $r['apiKey'] === 'ide-k' && $r['secretKey'] === 'ide-s');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/services/matching') && $r->data()['nationalCode'] === $this->nationalCode()
            && $r->data()['mobileNumber'] === '09121234567' && $r->hasHeader('Authorization', 'Bearer ide-token'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/identity/similarity') && $r->data()['birthDate'] === '13740101');
    }

    public function test_mismatch_or_outage_never_auto_approves(): void
    {
        app(Settings::class)->set('cashout.kyc_auto_approve', true);
        $this->fakeIde(mobile: false);
        $identity = $this->identity();
        $this->assertSame('failed', app(KycChecks::class)->identity($identity));
        $this->assertSame(UserIdentity::PENDING, $identity->fresh()->status);
    }

    public function test_provider_outage_is_unavailable_not_a_verdict(): void
    {
        app(Settings::class)->set('cashout.kyc_auto_approve', true);
        Http::fake(['*' => Http::response(null, 503)]);
        $identity = $this->identity();
        $this->assertSame('unavailable', app(KycChecks::class)->identity($identity));
        $this->assertSame(UserIdentity::PENDING, $identity->fresh()->status);
    }

    public function test_sheba_owner_and_account_status_are_checked(): void
    {
        $this->fakeIde(ibanStatus: 'BLOCK_WITH_DEPOSIT');
        $identity = $this->identity();
        $identity->forceFill(['status' => 'verified'])->save();
        $iban = $this->sheba();
        $account = BankAccount::query()->create(['user_id' => $this->user->id, 'iban' => $iban, 'iban_hash' => BankAccount::hashIban($iban), 'iban_last4' => substr($iban, -4),
            'bank_name' => 'ملی ایران', 'holder_name' => 'مریم احمدی', 'status' => 'pending']);
        $this->assertSame('failed', app(KycChecks::class)->bankAccount($account));
        $this->assertSame('BLOCK_WITH_DEPOSIT', $account->fresh()->auto_checks['account_status']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/services/matching') && ($r->data()['iban'] ?? null) === $iban && $r->data()['birthDate'] === '13740101');
    }

    private function approvedRequest(Admin $approver): CashoutRequest
    {
        $iban = $this->sheba();
        $account = BankAccount::query()->create(['user_id' => $this->user->id, 'iban' => $iban, 'iban_hash' => BankAccount::hashIban($iban), 'iban_last4' => substr($iban, -4),
            'bank_name' => 'ملی ایران', 'holder_name' => 'مریم احمدی', 'status' => 'verified']);
        app(WalletService::class)->credit($this->user, 10000, TransactionType::WalkingReward, 's', 'x');
        $r = CashoutRequest::query()->create(['user_id' => $this->user->id, 'bank_account_id' => $account->id, 'points' => 10000, 'rial_per_point' => 10,
            'amount_rial' => 100000, 'status' => 'pending', 'idempotency_key' => 'k']);
        $tx = app(WalletService::class)->debit($this->user, 10000, TransactionType::Cashout, 'cashout:'.$r->public_id, 'x', $r);
        $r->forceFill(['debit_transaction_id' => $tx->id])->save();

        return app(CashoutService::class)->approve($r, $approver);
    }

    public function test_settlement_is_sent_by_a_second_admin_and_followed_until_transferred(): void
    {
        $a = Admin::factory()->role(AdminRole::Finance)->create();
        $b = Admin::factory()->role(AdminRole::Finance)->create();
        $request = $this->approvedRequest($a);
        $state = 'RECEIVED';
        Http::fake([
            'napi.jibit.ir/cobank/v1/tokens/generate' => Http::response(['accessToken' => 'co-token']),
            'napi.jibit.ir/cobank/v1/orders/settlement' => Http::response(['referenceNumber' => 'REF1', 'records' => [['recordType' => 'PRIME', 'state' => 'RECEIVED']]]),
            'napi.jibit.ir/cobank/v1/orders/settlement/*' => function () use (&$state) {
                return Http::response(['referenceNumber' => 'REF1', 'records' => [['recordType' => 'PRIME', 'state' => $state, 'bankReferenceNumber' => 'BANK-777']]]);
            },
        ]);
        $svc = app(CashoutService::class);
        try {
            $svc->sendToBank($request, $a);
            $this->fail('approver sent');
        } catch (ApiException $e) {
            $this->assertSame('four_eyes', $e->errorCode);
        }

        $sent = $svc->sendToBank($request, $b);
        $this->assertSame(CashoutRequest::PROCESSING, $sent->status);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://napi.jibit.ir/cobank/v1/orders/settlement' && $r['destinationIban'] === $this->sheba()
            && $r['amount'] === 100000 && $r['recordTrackId'] === $sent->payout_track_id && $r->hasHeader('Authorization', 'Bearer co-token'));
        // Can't be rejected while the bank is moving the money.
        $this->expectRejectFails($sent, $a);

        $this->artisan('cashout:sync-payouts')->expectsOutput('Updated=1');
        $this->assertSame(CashoutRequest::PROCESSING, $sent->fresh()->status);
        $state = 'TRANSFERRED';
        $this->artisan('cashout:sync-payouts');
        $paid = $sent->fresh();
        $this->assertSame(CashoutRequest::PAID, $paid->status);
        $this->assertSame('BANK-777', $paid->bank_reference);
        $this->assertSame($b->id, $paid->paid_by);
        $this->artisan('ledger:reconcile')->expectsOutputToContain('status=ok');
    }

    private function expectRejectFails(CashoutRequest $r, Admin $admin): void
    {
        try {
            app(CashoutService::class)->reject($r, $admin, 'x');
            $this->fail('rejected during transfer');
        } catch (ApiException $e) {
            $this->assertSame('invalid_transition', $e->errorCode);
        }
    }

    public function test_failed_transfer_goes_back_to_finance(): void
    {
        $a = Admin::factory()->role(AdminRole::Finance)->create();
        $b = Admin::factory()->role(AdminRole::Finance)->create();
        $request = $this->approvedRequest($a);
        Http::fake([
            'napi.jibit.ir/cobank/v1/tokens/generate' => Http::response(['accessToken' => 'co-token']),
            'napi.jibit.ir/cobank/v1/orders/settlement' => Http::response(['records' => [['recordType' => 'PRIME', 'state' => 'FAILED', 'failReason' => 'BANK_ACCOUNT_NOT_ACTIVE']]]),
        ]);
        $r = app(CashoutService::class)->sendToBank($request, $b);
        $this->assertSame(CashoutRequest::APPROVED, $r->status);
        $this->assertSame('BANK_ACCOUNT_NOT_ACTIVE', $r->payout_error);
        $this->assertGreaterThan(0, $a->notifications()->count(), 'finance is alerted');
        // Finance may now reject (refund) it.
        app(CashoutService::class)->reject($r->fresh(), $a, 'حساب مقصد غیرفعال است');
        $this->assertSame(CashoutRequest::REJECTED, $r->fresh()->status);
    }
}
