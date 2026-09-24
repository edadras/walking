<?php

namespace App\Domain\Gamification;

use App\Models\DailyActivity;
use App\Models\User;
use App\Models\UserStreak;
use Carbon\CarbonImmutable;

/**
 * Consecutive days on which the goal was reached with VERIFIED steps, in the
 * user's own timezone. Recomputed from daily rows (never incremented blindly),
 * so late offline syncs and fraud reversals correct it automatically.
 */
class StreakService
{
    public function refresh(User $user): UserStreak
    {
        $today = CarbonImmutable::now($user->timezone)->startOfDay();
        $reached = DailyActivity::query()
            ->where('user_id', $user->id)
            ->whereNotNull('goal_reached_at')
            ->where('local_date', '>=', $today->subDays(400)->toDateString())
            ->orderByDesc('local_date')
            ->pluck('local_date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $set = array_flip($reached);
        // A streak survives until the end of today even if today's goal isn't met yet.
        $cursor = isset($set[$today->toDateString()]) ? $today : $today->subDay();
        $current = 0;
        while (isset($set[$cursor->toDateString()])) {
            $current++;
            $cursor = $cursor->subDay();
        }

        $longest = 0;
        $run = 0;
        $previous = null;
        foreach (array_reverse($reached) as $date) {
            $run = $previous !== null && CarbonImmutable::parse($previous)->addDay()->toDateString() === $date ? $run + 1 : 1;
            $longest = max($longest, $run);
            $previous = $date;
        }

        $streak = UserStreak::query()->firstOrNew(['user_id' => $user->id]);
        $streak->fill([
            'current_days' => $current,
            'longest_days' => max($longest, $streak->longest_days ?? 0),
            'last_goal_date' => $reached[0] ?? null,
        ])->save();

        return $streak;
    }

    /** Days of the current Iranian week (Sat→Fri) with the goal reached, for the home streak row. */
    public function weekDots(User $user): array
    {
        $today = CarbonImmutable::now($user->timezone)->startOfDay();
        $start = $today->subDays(($today->dayOfWeek + 1) % 7);
        $reached = DailyActivity::query()
            ->where('user_id', $user->id)
            ->whereBetween('local_date', [$start->toDateString(), $start->addDays(6)->toDateString()])
            ->whereNotNull('goal_reached_at')
            ->pluck('local_date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $dots = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->addDays($i);
            $dots[] = ['date' => $d->toDateString(), 'reached' => in_array($d->toDateString(), $reached, true), 'future' => $d->gt($today)];
        }

        return $dots;
    }
}
