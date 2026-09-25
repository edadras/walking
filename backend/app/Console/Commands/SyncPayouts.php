<?php

namespace App\Console\Commands;

use App\Domain\Cashout\CashoutService;
use Illuminate\Console\Command;

class SyncPayouts extends Command
{
    protected $signature = 'cashout:sync-payouts';

    protected $description = 'Follow bank transfers sent through the settlement API';

    public function handle(CashoutService $cashout): int
    {
        $this->info('Updated='.$cashout->syncPayouts());

        return self::SUCCESS;
    }
}
