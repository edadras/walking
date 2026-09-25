<?php

namespace App\Console\Commands;

use App\Domain\Settings\FeatureFlags;
use App\Domain\Wallet\WalletService;
use App\Enums\TransactionType;
use App\Models\BankAccount;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Models\UserIdentity;
use App\Support\Phone;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Prepares the account the emulator E2E test signs in with: a reserved load-test number
 * (fixed OTP via LOADTEST_OTP_CODE), aged points and a verified payout identity + Sheba,
 * so the test can buy in the store and request a cash-out. Refuses to run in production.
 */
class E2ePrepare extends Command
{
    protected $signature = 'e2e:prepare {phone=09990000001}';

    protected $description = 'Create the emulator end-to-end test account (non-production only)';

    public function handle(WalletService $wallet, FeatureFlags $flags): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }
        $phone = Phone::normalize($this->argument('phone'));
        if ($phone === null || ! str_starts_with($phone, '+98999')) {
            $this->error('Use a reserved +98999… load-test number.');

            return self::FAILURE;
        }
        foreach (['store', 'cashout'] as $flag) {
            FeatureFlag::query()->updateOrCreate(['key' => $flag], ['is_enabled' => true, 'rollout_percent' => 100]);
        }
        $flags->flush();

        $user = User::query()->where('phone', $phone)->first() ?? User::factory()->create(['phone' => $phone, 'display_name' => 'کاربر آزمون']);
        $user->forceFill(['created_at' => now()->subDays(60)])->save();

        $this->travelCredit($wallet, $user);

        $code = '0499370899';
        UserIdentity::query()->updateOrCreate(['user_id' => $user->id], ['first_name' => 'آزمون', 'last_name' => 'خودکار', 'national_code' => $code,
            'national_code_hash' => UserIdentity::hashNationalCode($code), 'birth_date' => '1990-01-01', 'status' => UserIdentity::VERIFIED, 'submitted_at' => now()]);
        $iban = 'IR062960000000100324200001';
        BankAccount::withTrashed()->where('iban_hash', BankAccount::hashIban($iban))->where('user_id', '!=', $user->id)->forceDelete();
        BankAccount::withTrashed()->updateOrCreate(['iban_hash' => BankAccount::hashIban($iban)], ['user_id' => $user->id, 'iban' => $iban, 'iban_last4' => substr($iban, -4),
            'bank_name' => 'بانک نامشخص', 'holder_name' => 'آزمون خودکار', 'status' => BankAccount::VERIFIED, 'deleted_at' => null]);

        $this->info("E2E account ready: {$phone}");

        return self::SUCCESS;
    }

    /** 60 000 walking points earned 30 days ago, so they are withdrawable. */
    private function travelCredit(WalletService $wallet, User $user): void
    {
        Carbon::setTestNow(now()->subDays(30));
        try {
            $wallet->credit($user, 60000, TransactionType::WalkingReward, 'e2e:seed:'.$user->id, 'امتیاز آزمون E2E');
        } finally {
            Carbon::setTestNow();
        }
    }
}
