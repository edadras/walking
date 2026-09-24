<?php

namespace App\Console\Commands;

use App\Domain\Challenge\ChallengeService;
use Illuminate\Console\Command;

class CloseChallenges extends Command
{
    protected $signature = 'challenges:close';

    protected $description = 'Mark challenges past their end time as ended';

    public function handle(ChallengeService $challenges): int
    {
        $this->info('Closed '.$challenges->closeFinished());

        return self::SUCCESS;
    }
}
