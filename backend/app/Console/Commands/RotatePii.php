<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use App\Models\UserIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * After rotating APP_KEY (old key in APP_PREVIOUS_KEYS) or PII_HASH_KEY: re-encrypts
 * national codes and Sheba numbers with the current APP_KEY and recomputes their
 * uniqueness hashes with the current PII_HASH_KEY. Idempotent; safe to re-run.
 * IP hashes are one-way and can't be recomputed (a new PII_HASH_KEY restarts IP clustering).
 */
class RotatePii extends Command
{
    protected $signature = 'pii:rotate';

    protected $description = 'Re-encrypt payout identities with the current APP_KEY and rehash them with PII_HASH_KEY';

    public function handle(): int
    {
        $ids = $accounts = 0;
        UserIdentity::query()->orderBy('user_id')->chunk(200, function ($rows) use (&$ids) {
            foreach ($rows as $identity) {
                $code = $identity->national_code; // decrypts with APP_KEY or any APP_PREVIOUS_KEYS
                DB::table('user_identities')->where('user_id', $identity->user_id)->update([
                    'national_code' => Crypt::encryptString($code),
                    'national_code_hash' => UserIdentity::hashNationalCode($code),
                ]);
                $ids++;
            }
        });
        BankAccount::withTrashed()->where('iban', '!=', '')->orderBy('id')->chunk(200, function ($rows) use (&$accounts) {
            foreach ($rows as $account) {
                if ($account->iban === '') {
                    continue; // erased after retention
                }
                DB::table('bank_accounts')->where('id', $account->id)->update([
                    'iban' => Crypt::encryptString($account->iban),
                    'iban_hash' => BankAccount::hashIban($account->iban),
                ]);
                $accounts++;
            }
        });
        $this->info("Rotated identities={$ids} bank_accounts={$accounts}");

        return self::SUCCESS;
    }
}
