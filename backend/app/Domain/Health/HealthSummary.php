<?php

namespace App\Domain\Health;

use App\Domain\Gamification\PersonalRecordService;
use App\Models\DailyActivity;
use App\Models\User;
use App\Models\UserStreak;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/** Activity statistics. Not medical advice: the client labels it as such. */
class HealthSummary
{
    public function __construct(private readonly PersonalRecordService $records) {}

    /** @return array<string, mixed> */
    public function summary(User $user, string $range): array
    {
        $today = CarbonImmutable::now($user->timezone)->startOfDay();
        $from = $range === 'month' ? $today->subDays(29) : $today->subDays(6);
        $rows = DailyActivity::query()->where('user_id', $user->id)->whereBetween('local_date', [$from->toDateString(), $today->toDateString()])->get()
            ->keyBy(fn ($d) => $d->local_date->toDateString());

        $days = [];
        foreach (CarbonPeriod::create($from, $today) as $d) {
            $r = $rows[$d->toDateString()] ?? null;
            $days[] = [
                'date' => $d->toDateString(),
                'steps' => $r?->verified_steps ?? 0,
                'raw_steps' => $r?->raw_steps ?? 0,
                'distance_m' => $r?->distance_m ?? 0,
                'calories_kcal' => $r?->calories_kcal ?? 0,
                'active_minutes' => $r?->active_minutes ?? 0,
                'goal' => $r?->goal_steps ?? ($user->profile?->daily_step_goal ?? 7500),
                'goal_reached' => $r?->goal_reached_at !== null,
            ];
        }

        $count = count($days);
        $sum = fn (string $k) => array_sum(array_column($days, $k));
        $streak = UserStreak::query()->find($user->id);

        return [
            'range' => $range,
            'days' => $days,
            'totals' => [
                'steps' => $sum('steps'),
                'distance_m' => $sum('distance_m'),
                'calories_kcal' => round($sum('calories_kcal'), 1),
                'active_minutes' => $sum('active_minutes'),
                'goal_days' => count(array_filter($days, fn ($d) => $d['goal_reached'])),
            ],
            'averages' => [
                'daily_steps' => (int) round($sum('steps') / $count),
                'weekly_steps' => (int) round($this->average($user, $today, 28) * 7),
                'monthly_steps' => (int) round($this->average($user, $today, 90) * 30),
            ],
            'streak' => ['current' => $streak?->current_days ?? 0, 'longest' => $streak?->longest_days ?? 0],
            'records' => $this->records->refresh($user),
        ];
    }

    /** This week vs last week (Sat→Fri, up to today). */
    public function weeklyReport(User $user): array
    {
        $today = CarbonImmutable::now($user->timezone)->startOfDay();
        $start = $today->subDays(($today->dayOfWeek + 1) % 7);
        $elapsed = $start->diffInDays($today);

        $week = fn (CarbonImmutable $s, int $len) => DailyActivity::query()->where('user_id', $user->id)
            ->whereBetween('local_date', [$s->toDateString(), $s->addDays($len)->toDateString()])
            ->selectRaw('COALESCE(SUM(verified_steps),0) steps, COALESCE(SUM(distance_m),0) distance, COALESCE(SUM(calories_kcal),0) kcal, COALESCE(SUM(points_earned),0) points, SUM(CASE WHEN goal_reached_at IS NOT NULL THEN 1 ELSE 0 END) goal_days, COALESCE(SUM(active_minutes),0) minutes')
            ->first();

        $current = $week($start, 6);
        // Compare like with like: the same number of elapsed days last week.
        $previous = $week($start->subDays(7), (int) $elapsed);
        $change = (int) $previous->steps > 0 ? (int) round(((int) $current->steps - (int) $previous->steps) * 100 / (int) $previous->steps) : null;

        return [
            'week_start' => $start->toDateString(),
            'days_elapsed' => (int) $elapsed + 1,
            'steps' => (int) $current->steps,
            'distance_m' => (int) $current->distance,
            'calories_kcal' => round((float) $current->kcal, 1),
            'points' => (int) $current->points,
            'active_minutes' => (int) $current->minutes,
            'goal_days' => (int) $current->goal_days,
            'previous_steps' => (int) $previous->steps,
            'change_percent' => $change,
        ];
    }

    private function average(User $user, CarbonImmutable $today, int $days): float
    {
        return (float) DailyActivity::query()->where('user_id', $user->id)
            ->whereBetween('local_date', [$today->subDays($days - 1)->toDateString(), $today->toDateString()])
            ->sum('verified_steps') / $days;
    }
}
