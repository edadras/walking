<?php

namespace App\Domain\Quest;

use App\Domain\Gamification\XpService;
use App\Domain\Settings\FeatureFlags;
use App\Domain\Wallet\WalletService;
use App\Enums\QuestMetric;
use App\Enums\SessionKind;
use App\Enums\SessionStatus;
use App\Enums\TransactionType;
use App\Enums\VisitStatus;
use App\Exceptions\ApiException;
use App\Models\DailyActivity;
use App\Models\Quest;
use App\Models\QuestClaim;
use App\Models\User;
use App\Models\Visit;
use App\Models\WalkingSession;
use App\Models\WaterLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Daily and weekly missions. Progress is always recomputed from verified data in
 * the user's timezone (Iranian week: Saturday → Friday); nothing the app reports
 * about a quest is trusted. Claiming pays through the ledger with the usual hold.
 */
class QuestService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly XpService $xp,
        private readonly FeatureFlags $flags,
    ) {}

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} start, end (inclusive dates), period key */
    public function window(User $user, string $period, ?CarbonImmutable $at = null): array
    {
        $today = ($at ?? CarbonImmutable::now($user->timezone))->setTimezone($user->timezone)->startOfDay();
        if ($period === 'daily') {
            return [$today, $today, $today->toDateString()];
        }
        $start = $today->subDays(($today->dayOfWeek + 1) % 7);

        return [$start, $start->addDays(6), 'w'.$start->toDateString()];
    }

    public function progress(User $user, Quest $quest): int
    {
        [$from, $to] = $this->window($user, $quest->period);
        $dates = [$from->toDateString(), $to->toDateString()];
        $daily = fn () => DailyActivity::query()->where('user_id', $user->id)->whereBetween('local_date', $dates);

        return (int) match ($quest->metric) {
            QuestMetric::Steps => $daily()->sum('verified_steps'),
            QuestMetric::ActiveMinutes => $daily()->sum('active_minutes'),
            QuestMetric::DistanceM => $daily()->sum('distance_m'),
            QuestMetric::GoalDays => $daily()->whereNotNull('goal_reached_at')->count(),
            QuestMetric::ActiveWalks => WalkingSession::query()->where('user_id', $user->id)->where('kind', SessionKind::Active)
                ->whereIn('status', [SessionStatus::Verified, SessionStatus::PartiallyVerified])->whereBetween('local_date', $dates)->count(),
            QuestMetric::WaterMl => WaterLog::query()->where('user_id', $user->id)->whereBetween('local_date', $dates)->sum('amount_ml'),
            QuestMetric::SponsorVisits => Visit::query()->where('user_id', $user->id)->whereIn('status', [VisitStatus::Verified, VisitStatus::Rewarded])
                ->whereBetween('entered_at', [$from->utc(), $to->endOfDay()->utc()])->count(),
        };
    }

    /** @return list<array<string, mixed>> */
    public function list(User $user): array
    {
        if (! $this->flags->enabled('quests', $user->id)) {
            return [];
        }
        $quests = Quest::query()->where('is_active', true)->orderByRaw("period = 'weekly'")->orderBy('sort')->get();
        $claims = QuestClaim::query()->where('user_id', $user->id)
            ->whereIn('period_key', [$this->window($user, 'daily')[2], $this->window($user, 'weekly')[2]])
            ->get()->keyBy(fn (QuestClaim $c) => $c->quest_id.'|'.$c->period_key);

        return $quests->map(function (Quest $q) use ($user, $claims) {
            [, $end, $key] = $this->window($user, $q->period);
            $progress = $this->progress($user, $q);

            return [
                'key' => $q->key,
                'title' => $q->title,
                'description' => $q->description,
                'period' => $q->period,
                'metric' => $q->metric->value,
                'unit' => $q->metric->unit(),
                'target' => $q->target,
                'progress' => min($progress, $q->target),
                'reward_points' => $q->reward_points,
                'reward_xp' => $q->reward_xp,
                'claimed' => $claims->has($q->id.'|'.$key),
                'claimable' => $progress >= $q->target && ! $claims->has($q->id.'|'.$key),
                'ends_on' => $end->toDateString(),
            ];
        })->values()->all();
    }

    public function claim(User $user, string $questKey): QuestClaim
    {
        if (! $this->flags->enabled('quests', $user->id)) {
            throw ApiException::forbidden('feature_disabled', 'مأموریت‌ها در حال حاضر فعال نیستند.');
        }
        $quest = Quest::query()->where('key', $questKey)->where('is_active', true)->first()
            ?? throw ApiException::unprocessable('quest_not_found', 'این مأموریت پیدا نشد.');
        [, , $periodKey] = $this->window($user, $quest->period);

        if ($existing = QuestClaim::query()->where(['user_id' => $user->id, 'quest_id' => $quest->id, 'period_key' => $periodKey])->first()) {
            return $existing;
        }
        $progress = $this->progress($user, $quest);
        if ($progress < $quest->target) {
            throw ApiException::conflict('quest_incomplete', 'هنوز این مأموریت کامل نشده است.');
        }

        try {
            return DB::transaction(function () use ($user, $quest, $periodKey, $progress) {
                $claim = QuestClaim::query()->create([
                    'user_id' => $user->id, 'quest_id' => $quest->id, 'period_key' => $periodKey, 'progress' => $progress, 'claimed_at' => now(),
                ]);
                if ($quest->reward_points > 0) {
                    $tx = $this->wallet->hold($user, $quest->reward_points, TransactionType::QuestReward,
                        "quest:{$quest->id}:{$periodKey}", 'پاداش مأموریت: '.$quest->title, $claim);
                    $claim->forceFill(['point_transaction_id' => $tx->id])->save();
                }
                $this->xp->award($user, $quest->reward_xp, 'quest', "quest:{$quest->id}:{$periodKey}");

                return $claim;
            });
        } catch (UniqueConstraintViolationException) {
            // A parallel double tap: the first claim won and paid once.
            return QuestClaim::query()->where(['user_id' => $user->id, 'quest_id' => $quest->id, 'period_key' => $periodKey])->firstOrFail();
        }
    }
}
