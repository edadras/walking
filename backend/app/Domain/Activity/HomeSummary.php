<?php

namespace App\Domain\Activity;

use App\Domain\Wallet\WalletSummary;
use App\Enums\SessionStatus;
use App\Models\DailyActivity;
use App\Models\User;
use App\Models\WalkingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Everything the home screen needs in one cached payload (one request per open).
 * Later phases add wallet, streak, challenges and ad placements here.
 */
class HomeSummary
{
    public static function cacheKey(User $user): string
    {
        return 'home:v1:'.$user->id;
    }

    public function __construct(private readonly WalletSummary $wallet) {}

    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        return Cache::remember(self::cacheKey($user), 60, function () use ($user) {
            $today = CarbonImmutable::now($user->timezone)->startOfDay();
            $goal = $user->profile?->daily_step_goal ?? 7500;

            $days = DailyActivity::query()
                ->where('user_id', $user->id)
                ->whereBetween('local_date', [$today->subDays(6)->toDateString(), $today->toDateString()])
                ->get()
                ->keyBy(fn (DailyActivity $d) => $d->local_date->toDateString());

            $row = $days->get($today->toDateString());
            $pending = WalkingSession::query()
                ->where('user_id', $user->id)
                ->where('local_date', $today->toDateString())
                ->where('status', SessionStatus::Submitted)
                ->sum('raw_steps');

            $week = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = $today->subDays($i)->toDateString();
                $d = $days->get($date);
                $week[] = [
                    'date' => $date,
                    'steps' => $d?->raw_steps ?? 0,
                    'goal' => $d?->goal_steps ?? $goal,
                ];
            }

            return [
                'today' => [
                    'date' => $today->toDateString(),
                    'steps' => $row?->raw_steps ?? 0,
                    'verified_steps' => $row?->verified_steps ?? 0,
                    'pending_steps' => (int) $pending,
                    'goal' => $goal,
                    'distance_m' => $row?->distance_m ?? 0,
                    'calories_kcal' => $row?->calories_kcal ?? 0,
                    'active_minutes' => $row?->active_minutes ?? 0,
                    'goal_reached' => $row?->goal_reached_at !== null,
                    'points' => $row?->points_earned ?? 0,
                    'last_synced_at' => ($last = WalkingSession::query()->where('user_id', $user->id)->max('created_at')) ? CarbonImmutable::parse($last, 'UTC')->toIso8601String() : null,
                ],
                'week' => $week,
                'wallet' => $this->wallet->for($user),
            ];
        });
    }
}
