<?php

namespace Tests\Feature\Security;

use App\Domain\Cashout\CashoutService;
use App\Domain\Wallet\WalletService;
use App\Enums\TransactionType;
use App\Enums\UserStatus;
use App\Models\AccountDeletionRequest;
use App\Models\Address;
use App\Models\BankAccount;
use App\Models\CashoutRequest;
use App\Models\User;
use App\Models\UserIdentity;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesCashoutData;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use CreatesCashoutData, RefreshDatabase, SignsDeviceRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        Storage::fake('public');
    }

    private function kyc(User $user, string $first9 = '001234567', string $account = '0000000123456789012'): BankAccount
    {
        UserIdentity::query()->create(['user_id' => $user->id, 'first_name' => 'مریم', 'last_name' => 'احمدی', 'national_code' => $this->nationalCode($first9),
            'national_code_hash' => UserIdentity::hashNationalCode($this->nationalCode($first9)), 'birth_date' => '1990-01-01', 'status' => 'verified', 'submitted_at' => now()]);
        $iban = $this->sheba('012', $account);

        return BankAccount::query()->create(['user_id' => $user->id, 'iban' => $iban, 'iban_hash' => BankAccount::hashIban($iban), 'iban_last4' => substr($iban, -4),
            'bank_name' => 'ملت', 'holder_name' => 'مریم احمدی', 'status' => 'verified']);
    }

    private function cashout(User $user, BankAccount $account, string $status): CashoutRequest
    {
        app(WalletService::class)->credit($user, 10000, TransactionType::WalkingReward, 'seed-'.$status, 'x');
        $r = CashoutRequest::query()->create(['user_id' => $user->id, 'bank_account_id' => $account->id, 'points' => 5000, 'rial_per_point' => 10,
            'amount_rial' => 50000, 'status' => CashoutRequest::PENDING, 'idempotency_key' => 'k-'.$status]);
        app(WalletService::class)->debit($user, 5000, TransactionType::Cashout, 'cashout:'.$r->public_id, 'x', $r);
        $r->forceFill(['status' => $status])->save();

        return $r;
    }

    private function requestDeletion(): User
    {
        $user = $this->loginAs('09121112233');
        $this->signedJson('POST', '/api/v1/me/deletion-request')->assertSuccessful();

        return $user->fresh();
    }

    public function test_nothing_happens_during_the_grace_period(): void
    {
        $user = $this->requestDeletion();
        $this->artisan('accounts:process-deletions')->assertSuccessful();
        $this->assertSame('+989121112233', $user->fresh()->phone);
    }

    public function test_account_is_anonymised_after_the_grace_period(): void
    {
        $user = $this->requestDeletion();
        $this->call('POST', '/api/v1/me/avatar', [], [], ['avatar' => UploadedFile::fake()->image('a.jpg', 300, 300)], $this->transformHeadersToServerVars(['Authorization' => 'Bearer '.$this->token, 'Accept' => 'application/json']))->assertOk();
        $avatar = $user->fresh()->avatar_path;
        Storage::disk('public')->assertExists($avatar);
        Address::query()->forceCreate(['user_id' => $user->id, 'public_id' => (string) str()->ulid(), 'title' => 'خانه', 'recipient' => 'مریم', 'phone' => '09121112233',
            'province' => 'تهران', 'city' => 'تهران', 'line' => 'خیابان آزادی', 'postal_code' => '1234567890']);
        $this->kyc($user); // KYC without any payout: nothing to keep

        $this->travel(15)->days();
        $this->artisan('accounts:process-deletions')->expectsOutputToContain('Deleted=1')->assertSuccessful();

        $gone = User::withTrashed()->find($user->id);
        $this->assertSame('del-'.$user->id, $gone->phone);
        $this->assertNull($gone->display_name);
        $this->assertSame(UserStatus::Deleted, $gone->status);
        $this->assertNotNull($gone->deleted_at);
        Storage::disk('public')->assertMissing($avatar);
        $this->assertSame(0, Address::query()->where('user_id', $user->id)->count());
        $this->assertNull(UserIdentity::query()->find($user->id));
        $this->assertSame(0, BankAccount::withTrashed()->where('user_id', $user->id)->count());
        $this->assertSame('completed', AccountDeletionRequest::query()->where('user_id', $user->id)->value('status'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.deleted']);
        // The old session is dead.
        $this->app['auth']->forgetGuards();
        $this->authedJson('GET', '/api/v1/me')->assertUnauthorized();
        // The phone number is free to register again.
        $this->loginAs('09121112233');
        $this->assertSame(2, User::withTrashed()->count());
    }

    public function test_approved_payout_defers_deletion_and_pending_one_is_withdrawn(): void
    {
        $user = $this->requestDeletion();
        $account = $this->kyc($user);
        $approved = $this->cashout($user, $account, CashoutRequest::APPROVED);
        $this->travel(15)->days();
        $this->artisan('accounts:process-deletions')->expectsOutputToContain('deferred=1');
        $this->assertSame('+989121112233', $user->fresh()->phone);

        $approved->forceFill(['status' => CashoutRequest::PAID])->save();
        $pending = $this->cashout($user, $account, 'pending');
        $this->artisan('accounts:process-deletions')->expectsOutputToContain('Deleted=1');
        $this->assertSame(CashoutRequest::CANCELLED, $pending->fresh()->status);

        // Payout identity is kept (encrypted) for the retention period, then erased.
        $identity = UserIdentity::query()->find($user->id);
        $this->assertNotNull($identity->retain_until);
        $this->travel(1826)->days();
        $this->artisan('accounts:process-deletions')->expectsOutputToContain('kyc_erased=1');
        $this->assertNull(UserIdentity::query()->find($user->id));
        $left = BankAccount::withTrashed()->where('user_id', $user->id)->first();
        $this->assertSame('', $left->iban);
        $this->assertSame(substr($account->iban, -4), $left->iban_last4);
        $this->assertSame(CashoutRequest::PAID, $approved->fresh()->status);
    }

    public function test_deletion_request_blocks_cashout(): void
    {
        $user = $this->requestDeletion();
        $this->assertContains('deletion_requested', array_column(app(CashoutService::class)->overview($user)['blockers'], 'code'));
    }
}
