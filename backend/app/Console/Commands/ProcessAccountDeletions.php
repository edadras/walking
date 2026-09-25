<?php

namespace App\Console\Commands;

use App\Domain\User\AccountPurger;
use App\Models\AccountDeletionRequest;
use Illuminate\Console\Command;

class ProcessAccountDeletions extends Command
{
    protected $signature = 'accounts:process-deletions';

    protected $description = 'Anonymise accounts whose deletion grace period has ended and erase expired payout identities';

    public function handle(AccountPurger $purger): int
    {
        $done = $deferred = 0;
        AccountDeletionRequest::query()->where('status', 'pending')->where('scheduled_for', '<=', now())->orderBy('id')
            ->each(function (AccountDeletionRequest $r) use ($purger, &$done, &$deferred) {
                $purger->purge($r) ? $done++ : $deferred++;
            });
        $kyc = $purger->purgeExpiredKyc();
        $this->info("Deleted={$done} deferred={$deferred} kyc_erased={$kyc}");

        return self::SUCCESS;
    }
}
