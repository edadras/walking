<?php

namespace Tests\Feature\Security;

use App\Models\BankAccount;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesCashoutData;
use Tests\TestCase;

class PiiKeyRotationTest extends TestCase
{
    use CreatesCashoutData, RefreshDatabase;

    private function useKeys(string $appKey, array $previous, string $piiKey): void
    {
        config(['app.key' => $appKey, 'app.previous_keys' => $previous, 'walk.security.pii_hash_key' => $piiKey]);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
    }

    public function test_hashes_do_not_depend_on_app_key(): void
    {
        $this->useKeys('base64:'.base64_encode(random_bytes(32)), [], 'pii-1');
        $a = UserIdentity::hashNationalCode('0499370899');
        $this->useKeys('base64:'.base64_encode(random_bytes(32)), [], 'pii-1');
        $this->assertSame($a, UserIdentity::hashNationalCode('0499370899'));
    }

    public function test_rotation_reencrypts_and_rehashes(): void
    {
        $old = 'base64:'.base64_encode(Encrypter::generateKey('aes-256-cbc'));
        $new = 'base64:'.base64_encode(Encrypter::generateKey('aes-256-cbc'));
        $this->useKeys($old, [], 'pii-old');
        $user = User::factory()->create();
        $code = $this->nationalCode();
        $iban = $this->sheba();
        UserIdentity::query()->create(['user_id' => $user->id, 'first_name' => 'مریم', 'last_name' => 'احمدی', 'national_code' => $code,
            'national_code_hash' => UserIdentity::hashNationalCode($code), 'birth_date' => '1990-01-01', 'status' => 'verified', 'submitted_at' => now()]);
        BankAccount::query()->create(['user_id' => $user->id, 'iban' => $iban, 'iban_hash' => BankAccount::hashIban($iban), 'iban_last4' => substr($iban, -4),
            'bank_name' => 'ملی ایران', 'holder_name' => 'مریم احمدی', 'status' => 'verified']);

        // New keys; the old APP_KEY stays readable through previous_keys.
        $this->useKeys($new, [$old], 'pii-new');
        $this->artisan('pii:rotate')->expectsOutputToContain('identities=1 bank_accounts=1')->assertSuccessful();

        // Readable with the new key alone, and found by the new hashes.
        $this->useKeys($new, [], 'pii-new');
        $identity = UserIdentity::query()->where('national_code_hash', UserIdentity::hashNationalCode($code))->firstOrFail();
        $this->assertSame($code, $identity->national_code);
        $this->assertSame($iban, BankAccount::query()->where('iban_hash', BankAccount::hashIban($iban))->firstOrFail()->iban);
        $this->assertStringNotContainsString($code, (string) DB::table('user_identities')->value('national_code'));
    }

    public function test_preflight_flags_missing_hash_key(): void
    {
        config(['walk.security.pii_hash_key' => null]);
        $this->artisan('ops:preflight')->expectsOutputToContain('PII_HASH_KEY is not set');
    }
}
