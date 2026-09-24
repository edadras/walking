<?php

namespace App\Console\Commands;

use App\Domain\Leaderboard\LeaderboardService;
use App\Support\Jalali;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Freezes yesterday's board, and the week/month boards when they just ended. */
class SnapshotLeaderboards extends Command
{
    protected $signature = 'leaderboard:snapshot';

    protected $description = 'Persist final rankings of finished leaderboard periods';

    public function handle(LeaderboardService $boards): int
    {
        $yesterday = CarbonImmutable::now('Asia/Tehran')->subDay()->startOfDay();
        $boards->snapshot('day', $yesterday);
        if ($yesterday->dayOfWeek === CarbonImmutable::FRIDAY) {
            $boards->snapshot('week', $yesterday);
        }
        if (Jalali::monthKey($yesterday) !== Jalali::monthKey($yesterday->addDay())) {
            $boards->snapshot('month', $yesterday);
        }

        return self::SUCCESS;
    }
}
