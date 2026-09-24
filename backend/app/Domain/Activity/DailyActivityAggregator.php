<?php

namespace App\Domain\Activity;

use App\Enums\SessionStatus;
use App\Models\DailyActivity;
use App\Models\User;
use App\Models\WalkingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds a user's daily row from their sessions. Always a full recompute
 * (never incremental arithmetic), so it is idempotent and self-healing.
 */
class DailyActivityAggregator
{
    public function refresh(User $user, CarbonImmutable|string $localDate): DailyActivity
    {
        $date = $localDate instanceof CarbonImmutable ? $localDate->toDateString() : $localDate;

        return DB::transaction(function () use ($user, $date) {
            $totals = WalkingSession::query()
                ->where('user_id', $user->id)
                ->where('local_date', $date)
                ->where('status', '!=', SessionStatus::Rejected)
                ->selectRaw('COALESCE(SUM(raw_steps),0) raw, COALESCE(SUM(COALESCE(verified_steps,0)),0) verified,
                    COALESCE(SUM(distance_m),0) distance, COALESCE(SUM(calories_kcal),0) kcal,
                    COALESCE(SUM(active_duration_s),0) active, COUNT(*) sessions')
                ->first();

            $daily = DailyActivity::query()
                ->where('user_id', $user->id)
                ->where('local_date', $date)
                ->lockForUpdate()
                ->first() ?? new DailyActivity(['user_id' => $user->id, 'local_date' => $date]);

            // The goal is a snapshot per day; only "today" follows later goal changes.
            $isToday = $date === now($user->timezone)->toDateString();
            if (! $daily->exists || $isToday) {
                $daily->goal_steps = $user->profile?->daily_step_goal ?? 7500;
            }

            $daily->fill([
                'raw_steps' => (int) $totals->raw,
                'verified_steps' => (int) $totals->verified,
                'distance_m' => (int) $totals->distance,
                'calories_kcal' => round((float) $totals->kcal, 1),
                'active_minutes' => min(1440, intdiv((int) $totals->active, 60)),
                'sessions_count' => (int) $totals->sessions,
            ]);

            // Goal completion counts verified steps only (it feeds streaks and bonuses).
            if ($daily->goal_reached_at === null && $daily->verified_steps >= $daily->goal_steps) {
                $daily->goal_reached_at = now();
            }

            $daily->save();

            DB::afterCommit(fn () => Cache::forget(HomeSummary::cacheKey($user)));

            return $daily;
        });
    }
}
