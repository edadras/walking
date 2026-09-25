<?php

namespace Tests\Feature\Cashout;

use App\Domain\Auth\OtpService;
use App\Domain\Cashout\CashoutService;
use App\Domain\Settings\FeatureFlags;
use App\Domain\Wallet\ConversionRate;
use App\Domain\Wallet\WalletService;
use App\Enums\AdminRole;
use App\Enums\FraudCaseStatus;
use App\Enums\TransactionType;
use App\Exceptions\ApiException;
use App\Jobs\SendOtpSms;
use App\Models\Admin;
use App\Models\BankAccount;
use App\Models\CashoutRequest;
use App\Models\FeatureFlag;
use App\Models\FraudCase;
use App\Models\User;
use App\Models\UserIdentity;
use App\Support\Ip;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesCashoutData;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class CashoutApiTest extends TestCase
{
    use CreatesCashoutData, RefreshDatabase, SignsDeviceRequests;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        // Route throttling has its own test; these tests go through many more steps per minute than a person.
        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithRedis::class]);
        FeatureFlag::query()->updateOrCreate(['key' => 'cashout'], ['is_enabled' => true, 'rollout_percent' => 100]);
        app(FeatureFlags::class)->flush();
        app(ConversionRate::class)->set(10);
        $this->user = $this->loginAs();
        $this->user->forceFill(['created_at' => now()->subDays(40)])->save();
        // Earned long enough ago to be withdrawable.
        $this->travel(-20)->days();
        app(WalletService::class)->credit($this->user, 100000, TransactionType::WalkingReward, 'seed', 'x');
        $this->travelBack();
    }

    /** Requests a cash-out SMS code the way the app does and returns it. */
    private function otp(): string
    {
        $this->clearOtpLimits($this->user);
        Bus::fake([SendOtpSms::class]);
        $this->signedJson('POST', '/api/v1/cashout/otp')->assertOk()->assertJsonStructure(['data' => ['expires_in', 'resend_in']]);
        $code = null;
        Bus::assertDispatched(SendOtpSms::class, function (SendOtpSms $job) use (&$code) {
            $code = $job->code;

            return $job->purpose === 'cashout' && $job->phone === $this->user->phone;
        });

        return $code;
    }

    /** Tests go through many more SMS codes than a real user would in an hour. */
    private function clearOtpLimits(User $user): void
    {
        foreach (['login', 'cashout'] as $p) {
            RateLimiter::clear("otp:resend:$p:{$user->phone}");
            RateLimiter::clear("otp:phone:$p:{$user->phone}");
            RateLimiter::clear("otp:verify:$p:{$user->phone}");
        }
        RateLimiter::clear('otp:device:'.$this->device->id);
        RateLimiter::clear('otp:ip:'.Ip::hash('127.0.0.1'));
    }

    private function identity(array $overrides = []): TestResponse
    {
        return $this->signedJson('POST', '/api/v1/cashout/identity', array_merge([
            'first_name' => 'مریم', 'last_name' => 'احمدی', 'national_code' => $this->nationalCode(),
            'birth_date' => '1995-03-21', 'code' => $this->otp(),
        ], $overrides));
    }

    private function verifiedSetup(): BankAccount
    {
        $this->identity()->assertOk();
        $admin = Admin::factory()->role(AdminRole::Support)->create();
        app(CashoutService::class)->reviewIdentity(UserIdentity::query()->findOrFail($this->user->id), true, $admin);
        $this->signedJson('POST', '/api/v1/cashout/bank-accounts', ['sheba' => $this->sheba(), 'code' => $this->otp()])->assertCreated();
        $account = BankAccount::query()->where('user_id', $this->user->id)->firstOrFail();
        app(CashoutService::class)->reviewBankAccount($account, true, $admin);

        return $account;
    }

    private function requestCashout(BankAccount $account, int $points, ?string $code = null, ?string $key = null): TestResponse
    {
        return $this->signedJson('POST', '/api/v1/cashout/requests',
            ['bank_account_id' => $account->public_id, 'points' => $points, 'code' => $code ?? $this->otp()],
            ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    public function test_overview_lists_what_is_missing(): void
    {
        $this->authedJson('GET', '/api/v1/cashout')->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.rial_per_point', 10)
            ->assertJsonPath('data.available_points', 100000)
            ->assertJsonPath('data.limits.min', 5000)
            ->assertJsonPath('data.limits.max', 50000)
            ->assertJsonPath('data.identity', null)
            ->assertJsonPath('data.blockers.0.code', 'identity_unverified')
            ->assertJsonPath('data.blockers.1.code', 'bank_account_unverified');
    }

    public function test_disabled_flag_blocks_every_write(): void
    {
        FeatureFlag::query()->where('key', 'cashout')->update(['is_enabled' => false]);
        app(FeatureFlags::class)->flush();
        $this->signedJson('POST', '/api/v1/cashout/otp')->assertForbidden()->assertJsonPath('error.code', 'feature_disabled');
        $this->authedJson('GET', '/api/v1/cashout')->assertOk()->assertJsonPath('data.blockers.0.code', 'feature_disabled');
    }

    public function test_writes_must_be_device_signed(): void
    {
        $this->authedJson('POST', '/api/v1/cashout/otp')->assertUnauthorized();
    }

    public function test_identity_needs_valid_national_code_adult_age_and_a_cashout_code(): void
    {
        $this->identity(['national_code' => '0499370898'])->assertUnprocessable()->assertJsonPath('error.code', 'national_code_invalid');
        $this->identity(['birth_date' => now()->subYears(15)->toDateString()])->assertUnprocessable()->assertJsonPath('error.code', 'age_restricted');
        $this->identity(['first_name' => 'Maryam'])->assertUnprocessable();
        $this->identity(['code' => '000000'])->assertUnprocessable();

    }

    public function test_a_login_code_never_confirms_a_payout_step(): void
    {
        $login = $this->serviceOtp($this->user, 'login');
        $cashout = $this->serviceOtp($this->user, 'cashout');
        if ($login !== $cashout) {
            try {
                app(CashoutService::class)->confirm($this->user, $login);
                $this->fail('login code accepted for cash-out');
            } catch (ApiException $e) {
                $this->assertSame('otp_invalid', $e->errorCode);
            }
        }
        // Issuing a login code didn't invalidate the cash-out one.
        app(CashoutService::class)->confirm($this->user, $cashout);
        $this->assertTrue(true);
    }

    /** Issues a code straight from the service (no HTTP session needed). */
    private function serviceOtp(User $user, string $purpose = 'cashout'): string
    {
        $this->clearOtpLimits($user);
        Bus::fake([SendOtpSms::class]);
        app(OtpService::class)->request($user->phone, $this->device, '127.0.0.1', $purpose);
        $code = null;
        Bus::assertDispatched(SendOtpSms::class, function ($job) use (&$code, $purpose) {
            $code = $job->code;

            return $job->purpose === $purpose;
        });

        return $code;
    }

    public function test_identity_is_stored_encrypted_masked_and_unique_per_person(): void
    {
        $this->identity()->assertOk()
            ->assertJsonPath('data.identity.status', 'pending')
            ->assertJsonPath('data.identity.national_code', '•••••••'.substr($this->nationalCode(), -3));
        $raw = DB::table('user_identities')->where('user_id', $this->user->id)->value('national_code');
        $this->assertStringNotContainsString($this->nationalCode(), $raw);

        $other = User::factory()->create(['phone' => '09350001111']);
        try {
            app(CashoutService::class)->submitIdentity($other, ['first_name' => 'علی', 'last_name' => 'رضایی', 'national_code' => $this->nationalCode(),
                'birth_date' => '1990-01-01'], $this->serviceOtp($other));
            $this->fail('same national code accepted twice');
        } catch (ApiException $e) {
            $this->assertSame('national_code_taken', $e->errorCode);
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'cashout.national_code_reused']);
    }

    public function test_verified_identity_is_locked(): void
    {
        $this->verifiedSetup();
        $this->identity(['first_name' => 'زهرا'])->assertConflict()->assertJsonPath('error.code', 'identity_locked');
    }

    public function test_bank_account_needs_identity_valid_sheba_and_is_unique(): void
    {
        $this->signedJson('POST', '/api/v1/cashout/bank-accounts', ['sheba' => $this->sheba(), 'code' => '123456'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'identity_required');
        $this->identity()->assertOk();
        $this->signedJson('POST', '/api/v1/cashout/bank-accounts', ['sheba' => 'IR072960000000100324200001', 'code' => '123456'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'sheba_invalid');
        $this->signedJson('POST', '/api/v1/cashout/bank-accounts', ['sheba' => $this->sheba('012'), 'code' => $this->otp()])->assertCreated()
            ->assertJsonPath('data.bank_accounts.0.bank_name', 'ملت')
            ->assertJsonPath('data.bank_accounts.0.holder_name', 'مریم احمدی')
            ->assertJsonPath('data.bank_accounts.0.status', 'pending');
        $this->signedJson('POST', '/api/v1/cashout/bank-accounts', ['sheba' => $this->sheba('012'), 'code' => $this->otp()])
            ->assertConflict()->assertJsonPath('error.code', 'sheba_taken');

        // Removing and re-adding the same account starts review over.
        $id = BankAccount::query()->where('user_id', $this->user->id)->value('public_id');
        $this->signedJson('DELETE', '/api/v1/cashout/bank-accounts/'.$id)->assertOk()->assertJsonCount(0, 'data.bank_accounts');
        $this->signedJson('POST', '/api/v1/cashout/bank-accounts', ['sheba' => $this->sheba('012'), 'code' => $this->otp()])->assertCreated()
            ->assertJsonPath('data.bank_accounts.0.id', $id);
    }

    public function test_full_flow_debits_immediately_and_needs_two_admins_to_pay(): void
    {
        $account = $this->verifiedSetup();
        $this->authedJson('GET', '/api/v1/cashout')->assertJsonPath('data.blockers', []);

        $key = (string) Str::uuid();
        $code = $this->otp();
        $r = $this->requestCashout($account, 20000, $code, $key)->assertCreated()
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.amount_rial', 200000);
        // Retry with the same key is the same request, not a second debit.
        $this->requestCashout($account, 20000, $code, $key)->assertOk()->assertJsonPath('data.id', $r->json('data.id'));
        $this->assertSame(80000, $this->user->wallet()->first()->available_balance);
        $this->authedJson('GET', '/api/v1/wallet/transactions?filter=cashout')->assertJsonCount(1, 'data')->assertJsonPath('data.0.amount', -20000);

        // One open request at a time.
        $this->requestCashout($account, 5000)->assertConflict()->assertJsonPath('error.code', 'request_open');

        $request = CashoutRequest::query()->where('public_id', $r->json('data.id'))->firstOrFail();
        $a = Admin::factory()->role(AdminRole::Finance)->create();
        $b = Admin::factory()->role(AdminRole::Finance)->create();
        $svc = app(CashoutService::class);
        $svc->approve($request, $a);
        try {
            $svc->markPaid($request, $a, 'REF-1');
            $this->fail('approver must not confirm payment');
        } catch (ApiException $e) {
            $this->assertSame('four_eyes', $e->errorCode);
        }
        $svc->markPaid($request, $b, 'REF-778899');
        $this->authedJson('GET', '/api/v1/cashout')->assertJsonPath('data.requests.0.status', 'paid')
            ->assertJsonPath('data.requests.0.bank_reference', 'REF-778899')
            ->assertJsonPath('data.limits.window_used', 20000);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->user->id]);
        $this->assertSame(80000, $this->user->wallet()->first()->available_balance);
    }

    public function test_limits_are_enforced(): void
    {
        $account = $this->verifiedSetup();
        $this->requestCashout($account, 4999)->assertUnprocessable()->assertJsonPath('error.code', 'amount_out_of_range');
        $this->requestCashout($account, 50001)->assertUnprocessable()->assertJsonPath('error.code', 'amount_out_of_range');

        // Rolling 30-day window counts paid and open requests, not rejected ones.
        foreach ([50000, 50000, 50000] as $i => $points) {
            CashoutRequest::query()->create(['user_id' => $this->user->id, 'bank_account_id' => $account->id, 'points' => $points, 'rial_per_point' => 10,
                'amount_rial' => $points * 10, 'status' => $i === 2 ? CashoutRequest::REJECTED : CashoutRequest::PAID, 'idempotency_key' => 'k'.$i]);
        }
        $this->authedJson('GET', '/api/v1/cashout')->assertJsonPath('data.limits.window_left', 50000);
        CashoutRequest::query()->where('idempotency_key', 'k2')->update(['status' => CashoutRequest::PAID]);
        $this->requestCashout($account, 5000)->assertUnprocessable()->assertJsonPath('error.code', 'window_limit');
    }

    public function test_cannot_withdraw_more_than_available(): void
    {
        $account = $this->verifiedSetup();
        app(WalletService::class)->debit($this->user, 96000, TransactionType::Purchase, 'p', 'x');
        $this->app['auth']->forgetGuards();
        $this->requestCashout($account, 5000)->assertConflict()->assertJsonPath('error.code', 'insufficient_points');
        $this->assertSame(0, CashoutRequest::query()->count());
    }

    public function test_blockers_new_account_fraud_case_and_unverified_account(): void
    {
        $account = $this->verifiedSetup();
        $this->user->forceFill(['created_at' => now()->subDays(3)])->save();
        $this->app['auth']->forgetGuards();
        $this->requestCashout($account, 5000)->assertConflict()->assertJsonPath('error.code', 'account_too_new');
        $this->user->forceFill(['created_at' => now()->subDays(40)])->save();
        $this->app['auth']->forgetGuards();

        FraudCase::query()->forceCreate(['user_id' => $this->user->id, 'status' => FraudCaseStatus::Open, 'reason' => 'test', 'risk_score' => 50]);
        $this->requestCashout($account, 5000)->assertConflict()->assertJsonPath('error.code', 'under_review');
    }

    public function test_user_cancel_and_admin_reject_refund_through_the_ledger(): void
    {
        $account = $this->verifiedSetup();
        $id = $this->requestCashout($account, 10000)->json('data.id');
        $this->signedJson('POST', "/api/v1/cashout/requests/$id/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(100000, $this->user->wallet()->first()->available_balance);
        $this->signedJson('POST', "/api/v1/cashout/requests/$id/cancel")->assertConflict();

        $id = $this->requestCashout($account, 10000)->json('data.id');
        $request = CashoutRequest::query()->where('public_id', $id)->firstOrFail();
        app(CashoutService::class)->approve($request, Admin::factory()->role(AdminRole::Finance)->create());
        // Approved requests can't be cancelled by the user anymore (the transfer may be under way).
        $this->signedJson('POST', "/api/v1/cashout/requests/$id/cancel")->assertConflict();
        app(CashoutService::class)->reject($request, Admin::factory()->role(AdminRole::Finance)->create(), 'نام صاحب حساب مطابقت ندارد');
        $this->assertSame(100000, $this->user->wallet()->first()->available_balance);
        $this->authedJson('GET', '/api/v1/cashout')->assertJsonPath('data.requests.0.status', 'rejected')
            ->assertJsonPath('data.requests.0.rejection_reason', 'نام صاحب حساب مطابقت ندارد');
    }

    public function test_other_users_requests_are_not_reachable(): void
    {
        $account = $this->verifiedSetup();
        $id = $this->requestCashout($account, 10000)->json('data.id');
        $this->loginAs('09350000000');
        $this->signedJson('POST', "/api/v1/cashout/requests/$id/cancel")->assertNotFound();
        $this->signedJson('DELETE', '/api/v1/cashout/bank-accounts/'.$account->public_id)->assertNotFound();
    }

    public function test_account_in_use_cannot_be_removed(): void
    {
        $account = $this->verifiedSetup();
        $this->requestCashout($account, 10000)->assertCreated();
        $this->signedJson('DELETE', '/api/v1/cashout/bank-accounts/'.$account->public_id)->assertConflict()->assertJsonPath('error.code', 'bank_account_in_use');
    }
}
