<?php

namespace App\Jobs;

use App\Domain\Cashout\KycChecks;
use App\Models\BankAccount;
use App\Models\UserIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Automated inquiry right after the user submits an identity or a Sheba. */
class RunKycChecks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public readonly string $kind, public readonly int $id) {}

    public function handle(KycChecks $checks): void
    {
        if (! $checks->enabled()) {
            return;
        }
        if ($this->kind === 'identity' && ($identity = UserIdentity::query()->find($this->id))) {
            $checks->identity($identity);
        }
        if ($this->kind === 'bank_account' && ($account = BankAccount::query()->find($this->id))) {
            $checks->bankAccount($account);
        }
    }
}
