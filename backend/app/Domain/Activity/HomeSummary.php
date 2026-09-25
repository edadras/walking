<?php

namespace App\Domain\Activity;

use App\Domain\Gamification\StreakService;
use App\Domain\Gamification\XpService;
use App\Domain\Health\WaterService;
use App\Domain\Wallet\WalletSummary;
use App\Enums\ChallengeStatus;
use App\Enums\SessionStatus;
use App\Models\ChallengeParticipant;
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
    /** The user's running challenge closest to completion, for the home card. */
    private function activeChallenge(User $user): ?array
    {
        $p = ChallengeParticipant::query()->where('user_id', $user->id)->whereNull('completed_at')
            ->whereHas('challenge', fn ($q) => $q->where('status', ChallengeStatus::Active)->where('ends_at', '>', now()))
            ->with('challenge')->get()
            ->sortByDesc(fn ($p) => $p->progress / max(1, $p->challenge->target_value))
            ->first();

        return $p === null ? null : [
            'id' => $p->challenge->public_id,
            'title' => $p->challenge->title,
            'progress' => $p->progress,
            'target' => $p->challenge->target_value,
            'metric' => $p->challenge->metric,
            'ends_at' => $p->challenge->ends_at->toIso8601String(),
        ];
    }

    public static function cacheKey(User $user): string
    {
        return 'home:v1:'.$user->id;
    }

    public function __construct(
        private readonly WalletSummary $wallet,
        private readonly StreakService $streaks,
        private readonly XpService $xp,
        private readonly WaterService $water,
    ) {}

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
                    'cycling_distance_m' => $row?->cycling_distance_m ?? 0,
                    'cycling_points' => $row?->cycling_points ?? 0,
                    'calories_kcal' => $row?->calories_kcal ?? 0,
                    'active_minutes' => $row?->active_minutes ?? 0,
                    'goal_reached' => $row?->goal_reached_at !== null,
                    'points' => $row?->points_earned ?? 0,
                    'last_synced_at' => ($last = WalkingSession::query()->where('user_id', $user->id)->max('created_at')) ? CarbonImmutable::parse($last, 'UTC')->toIso8601String() : null,
                ],
                'week' => $week,
                'wallet' => $this->wallet->for($user),
                'streak' => $this->streaks->summary($user),
                'level' => $this->xp->progress($user),
                'water' => array_intersect_key($this->water->day($user), array_flip(['total_ml', 'goal_ml', 'glass_ml', 'glasses', 'goal_glasses'])),
                'challenge' => $this->activeChallenge($user),
                'unread_notifications' => $user->unreadNotifications()->count(),
            ];
        });
    }
}
