<?php

namespace App\Console\Commands;

use App\Domain\Reward\PendingReleaser;
use Illuminate\Console\Command;

class ReleasePendingPoints extends Command
{
    protected $signature = 'wallet:release-pending {--limit=1000}';

    protected $description = 'Make reward holds spendable once their review window has passed';

    public function handle(PendingReleaser $releaser): int
    {
        $this->info('Released '.$releaser->run((int) $this->option('limit')).' transactions');

        return self::SUCCESS;
    }
}
