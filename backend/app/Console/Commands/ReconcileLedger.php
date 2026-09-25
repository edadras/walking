<?php

namespace App\Console\Commands;

use App\Domain\Wallet\LedgerReconciler;
use Illuminate\Console\Command;

class ReconcileLedger extends Command
{
    protected $signature = 'ledger:reconcile';

    protected $description = 'Check wallet balances and payouts against the points ledger (report only)';

    public function handle(LedgerReconciler $reconciler): int
    {
        $run = $reconciler->run();
        $this->info("status={$run->status} wallets={$run->wallets_checked} cashouts={$run->cashouts_checked} issues={$run->issue_count}");
        foreach (array_slice($run->issues, 0, 20) as $issue) {
            $this->line(json_encode($issue, JSON_UNESCAPED_UNICODE));
        }

        return $run->status === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}
