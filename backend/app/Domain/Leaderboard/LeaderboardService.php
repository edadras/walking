<?php

namespace App\Domain\Leaderboard;

use App\Enums\UserStatus;
use App\Models\DailyActivity;
use App\Models\LeaderboardSnapshot;
use App\Models\User;
use App\Support\Jalali;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

/**
 * Verified-step rankings for today / this week (Sat–Fri) / this Jalali month.
 *
 * Redis sorted sets hold the live boards; scores are SET (not incremented)
 * from the user's daily rows, so re-syncing is idempotent and fraud reversals
 * lower a score immediately. Users who hide themselves are removed from every
 * board. With driver=database the same answers come straight from MySQL.
 */
class LeaderboardService
{
    public const PERIODS = ['day', 'week', 'month'];

    private const TTL = 60 * 60 * 24 * 40;

    public function redis(): bool
    {
        return config('walk.leaderboard.driver') === 'redis';
    }

    /** @return array{0: string, 1: string, 2: string} [key, from, to] */
    public function period(string $period, CarbonImmutable $localDay): array
    {
        return match ($period) {
            'day' => ['day:'.$localDay->toDateString(), $localDay->toDateString(), $localDay->toDateString()],
            'week' => (function () use ($localDay) {
                $start = $localDay->subDays(($localDay->dayOfWeek + 1) % 7);

                return ['week:'.$start->toDateString(), $start->toDateString(), $start->addDays(6)->toDateString()];
            })(),
            'month' => (function () use ($localDay) {
                [$from, $to] = Jalali::monthRange($localDay);

                return ['month:'.Jalali::monthKey($localDay), $from, $to];
            })(),
        };
    }

    /** Recomputes the user's scores on all boards that contain $localDate. */
    public function sync(User $user, string $localDate): void
    {
        if (! $this->redis()) {
            return;
        }
        // Read persisted flags: an in-memory model may lack DB defaults or be stale.
        $user = User::query()->find($user->id, ['id', 'leaderboard_visible', 'status']) ?? $user;
        $day = CarbonImmutable::parse($localDate);
        foreach (self::PERIODS as $period) {
            [$key, $from, $to] = $this->period($period, $day);
            if (! $user->leaderboard_visible || $user->status !== UserStatus::Active) {
                Redis::zrem($this->redisKey($key), $user->id);

                continue;
            }
            $score = (int) DailyActivity::query()->where('user_id', $user->id)->whereBetween('local_date', [$from, $to])->sum('verified_steps');
            Redis::zadd($this->redisKey($key), $score, $user->id);
            Redis::expire($this->redisKey($key), self::TTL);
        }
    }

    /** Removes a user from the current boards (privacy switch, ban). */
    public function forget(User $user): void
    {
        if (! $this->redis()) {
            return;
        }
        $today = CarbonImmutable::now($user->timezone)->startOfDay();
        foreach (self::PERIODS as $period) {
            Redis::zrem($this->redisKey($this->period($period, $today)[0]), $user->id);
        }
    }

    /**
     * @return array{period: string, key: string, entries: list<array<string, mixed>>, me: ?array<string, mixed>}
     */
    public function board(string $period, User $viewer, ?int $limit = null): array
    {
        $limit ??= (int) config('walk.leaderboard.top');
        [$key, $from, $to] = $this->period($period, CarbonImmutable::now($viewer->timezone)->startOfDay());

        if ($this->redis()) {
            $rows = Redis::zrevrange($this->redisKey($key), 0, $limit - 1, ['withscores' => true]);
            $top = collect($rows)->map(fn ($score, $id) => ['user_id' => (int) $id, 'score' => (int) $score])->values();
            $rank = Redis::zrevrank($this->redisKey($key), $viewer->id);
            $myScore = Redis::zscore($this->redisKey($key), $viewer->id);
            $me = $rank === null || $rank === false ? null : ['rank' => $rank + 1, 'score' => (int) $myScore];
        } else {
            [$top, $me] = $this->fromDatabase($from, $to, $viewer, $limit);
        }

        $users = User::query()->with('profile')->whereIn('id', $top->pluck('user_id'))->get()->keyBy('id');
        $entries = [];
        foreach ($top as $i => $row) {
            $u = $users[$row['user_id']] ?? null;
            if ($u === null || ! $u->leaderboard_visible || $u->status !== UserStatus::Active) {
                continue;
            }
            $entries[] = [
                'rank' => $i + 1,
                'name' => $u->publicName(),
                'avatar_url' => $u->avatar_path ? Storage::disk('public')->url($u->avatar_path) : null,
                'level' => $u->level,
                'steps' => $row['score'],
                'is_me' => $u->id === $viewer->id,
            ];
        }

        return [
            'period' => $period,
            'key' => $key,
            'entries' => $entries,
            'me' => $me === null ? null : [...$me, 'name' => $viewer->publicName(), 'level' => $viewer->level, 'visible' => $viewer->leaderboard_visible],
        ];
    }

    /** @return array{0: Collection, 1: ?array} */
    private function fromDatabase(string $from, string $to, User $viewer, int $limit): array
    {
        $scores = DailyActivity::query()
            ->join('users', 'users.id', '=', 'daily_activities.user_id')
            ->where('users.leaderboard_visible', true)
            ->where('users.status', UserStatus::Active->value)
            ->whereBetween('daily_activities.local_date', [$from, $to])
            ->groupBy('daily_activities.user_id')
            ->selectRaw('daily_activities.user_id, SUM(daily_activities.verified_steps) score');

        $top = DB::query()->fromSub($scores, 's')->orderByDesc('score')->orderBy('user_id')->limit($limit)->get()
            ->map(fn ($r) => ['user_id' => (int) $r->user_id, 'score' => (int) $r->score]);

        $mine = DB::query()->fromSub($scores, 's')->where('user_id', $viewer->id)->value('score');
        $me = null;
        if ($mine !== null) {
            $better = DB::query()->fromSub($scores, 's')->where('score', '>', $mine)->count();
            $me = ['rank' => $better + 1, 'score' => (int) $mine];
        }

        return [$top, $me];
    }

    /** Persists final rankings of a finished period (for history and prizes). */
    public function snapshot(string $period, CarbonImmutable $day): int
    {
        [$key, $from, $to] = $this->period($period, $day);
        $scores = DailyActivity::query()
            ->join('users', 'users.id', '=', 'daily_activities.user_id')
            ->where('users.leaderboard_visible', true)->where('users.status', UserStatus::Active->value)
            ->whereBetween('daily_activities.local_date', [$from, $to])
            ->groupBy('daily_activities.user_id')
            ->selectRaw('daily_activities.user_id, SUM(daily_activities.verified_steps) score')
            ->orderByDesc('score')->limit(1000)->get();

        $rows = [];
        foreach ($scores as $i => $s) {
            $rows[] = ['board' => 'steps', 'period' => $period, 'period_key' => explode(':', $key)[1], 'user_id' => $s->user_id, 'rank' => $i + 1, 'score' => (int) $s->score, 'created_at' => now()];
        }
        LeaderboardSnapshot::query()->upsert($rows, ['board', 'period', 'period_key', 'user_id'], ['rank', 'score']);

        return count($rows);
    }

    private function redisKey(string $key): string
    {
        return 'lb:steps:'.$key;
    }
}
