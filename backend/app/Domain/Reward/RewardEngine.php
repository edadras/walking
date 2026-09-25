<?php

namespace App\Domain\Reward;

use App\Domain\Settings\Settings;
use App\Domain\Wallet\WalletService;
use App\Enums\SessionRewardStatus;
use App\Enums\SessionStatus;
use App\Enums\TransactionType;
use App\Models\DailyActivity;
use App\Models\Reward;
use App\Models\User;
use App\Models\WalkingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Server-side points for verified steps (docs/phase-0/05-flows.md §5.4).
 *
 * Points are computed on the day's running total, not per session, so small
 * sessions never lose points to rounding:
 *   base = points_for(rewarded_before + allowed) − points_for(rewarded_before)
 * then × multipliers (weekday, bonus hours, campaigns), then daily/weekly caps.
 * Everything runs under a lock on the user's daily row.
 */
class RewardEngine
{
    public function __construct(
        private readonly RewardRules $rules,
        private readonly WalletService $wallet,
        private readonly Settings $settings,
    ) {}

    public function forSession(WalkingSession $session): ?Reward
    {
        if (! in_array($session->status, [SessionStatus::Verified, SessionStatus::PartiallyVerified], true)) {
            return null;
        }

        $session->loadMissing('user.profile');

        $reward = DB::transaction(function () use ($session) {
            $user = $session->user;
            $date = $session->local_date->toDateString();
            $daily = DailyActivity::query()->where('user_id', $user->id)->where('local_date', $date)->lockForUpdate()->firstOrFail();

            $existing = Reward::query()->where(['source_type' => $session->getMorphClass(), 'source_id' => $session->id, 'user_id' => $user->id, 'kind' => 'walking'])->first();
            if ($existing !== null) {
                return $existing;
            }

            $at = $session->started_at;
            $local = $session->started_at->setTimezone($user->timezone);
            $rate = $this->rules->stepRate($at);
            $pointsFor = fn (int $steps) => intdiv($steps * $rate['points'], $rate['steps']);

            $maxSteps = $this->rules->maxRewardedSteps($at);
            $allowed = min((int) $session->verified_steps, max(0, $maxSteps - $daily->rewarded_steps));
            $base = $pointsFor($daily->rewarded_steps + $allowed) - $pointsFor($daily->rewarded_steps);

            $multiplier = $this->rules->multiplier($local);
            $points = (int) floor($base * $multiplier['factor']);

            $dailyRoom = max(0, $this->rules->dailyCap($at) - $daily->points_earned);
            $weeklyRoom = max(0, $this->rules->weeklyCap($at) - $this->weekPoints($user, $local));
            $final = min($points, $dailyRoom, $weeklyRoom);

            $daily->rewarded_steps += $allowed;
            $daily->points_earned += $final;
            $daily->save();

            $reward = Reward::query()->create([
                'user_id' => $user->id,
                'kind' => 'walking',
                'source_type' => $session->getMorphClass(),
                'source_id' => $session->id,
                'base_points' => $base,
                'multiplier' => $multiplier['factor'],
                'capped_points' => $points - $final,
                'final_points' => $final,
                'status' => $final > 0 ? 'pending' : 'denied',
                'breakdown' => [
                    'verified_steps' => (int) $session->verified_steps,
                    'rewarded_steps' => $allowed,
                    'rate' => $rate,
                    'max_rewarded_steps' => $maxSteps,
                    'multipliers' => $multiplier['applied'],
                    'daily_cap_room' => $dailyRoom,
                    'weekly_cap_room' => $weeklyRoom,
                ],
            ]);

            if ($final > 0) {
                $transaction = $this->wallet->hold($user, $final, TransactionType::WalkingReward, 'walking:'.$session->id, 'پاداش '.number_format($allowed).' قدم', $session, $this->availableAt());
                $reward->forceFill(['point_transaction_id' => $transaction->id])->save();
            }

            $cycling = $this->cycling($session, $daily, $weeklyRoom - $final);

            $session->forceFill(['reward_status' => $final + $cycling > 0 ? SessionRewardStatus::Pending : SessionRewardStatus::Denied])->save();

            $this->goalBonus($user, $daily);

            return $reward;
        });

        Cache::forget('home:v1:'.$session->user_id);

        return $reward;
    }

    /**
     * Distance on a bicycle, at the (much lower) per-km rate, inside its own daily cap and
     * whatever room the day's and week's overall caps still have. No multipliers.
     */
    private function cycling(WalkingSession $session, DailyActivity $daily, int $weeklyRoom): int
    {
        $meters = (int) $session->cycling_distance_m;
        if ($meters <= 0) {
            return 0;
        }
        $at = $session->started_at;
        $perKm = $this->settings->int('cycling.points_per_km');
        $points = intdiv($meters * $perKm, 1000);
        $room = min(
            max(0, $this->settings->int('cycling.daily_cap') - $daily->cycling_points),
            max(0, $this->rules->dailyCap($at) - $daily->points_earned),
            max(0, $weeklyRoom),
        );
        $final = min($points, $room);

        $reward = Reward::query()->create([
            'user_id' => $session->user_id,
            'kind' => 'cycling',
            'source_type' => $session->getMorphClass(),
            'source_id' => $session->id,
            'base_points' => $points,
            'multiplier' => 1,
            'capped_points' => $points - $final,
            'final_points' => $final,
            'status' => $final > 0 ? 'pending' : 'denied',
            'breakdown' => ['cycling_distance_m' => $meters, 'points_per_km' => $perKm, 'cap_room' => $room],
        ]);
        if ($final > 0) {
            $km = number_format($meters / 1000, 1);
            $transaction = $this->wallet->hold($session->user, $final, TransactionType::CyclingReward, 'cycling:'.$session->id, "پاداش {$km} کیلومتر دوچرخه‌سواری", $session, $this->availableAt());
            $reward->forceFill(['point_transaction_id' => $transaction->id])->save();
            $daily->cycling_points += $final;
            $daily->points_earned += $final;
            $daily->save();
        }

        return $final;
    }

    /** One-time bonus when the day's verified steps reach the goal snapshot. */
    private function goalBonus(User $user, DailyActivity $daily): void
    {
        $points = $this->rules->goalBonus(now());
        if ($points <= 0 || $daily->verified_steps < $daily->goal_steps) {
            return;
        }
        $exists = Reward::query()->where(['source_type' => $daily->getMorphClass(), 'source_id' => $daily->id, 'user_id' => $user->id, 'kind' => 'goal_bonus'])->exists();
        if ($exists) {
            return;
        }

        $reward = Reward::query()->create([
            'user_id' => $user->id,
            'kind' => 'goal_bonus',
            'source_type' => $daily->getMorphClass(),
            'source_id' => $daily->id,
            'bonus_points' => $points,
            'final_points' => $points,
            'status' => 'pending',
            'breakdown' => ['goal' => $daily->goal_steps, 'verified_steps' => $daily->verified_steps],
        ]);
        $transaction = $this->wallet->hold($user, $points, TransactionType::GoalBonus, 'goal:'.$daily->local_date->toDateString(), 'پاداش رسیدن به هدف روزانه', $daily, $this->availableAt());
        $reward->forceFill(['point_transaction_id' => $transaction->id])->save();
        $daily->points_earned += $points;
        $daily->save();
    }

    /** Points already earned this week (Iranian week: Saturday → Friday). */
    private function weekPoints(User $user, CarbonImmutable $local): int
    {
        $start = $local->startOfDay()->subDays(($local->dayOfWeek + 1) % 7);

        return (int) DailyActivity::query()
            ->where('user_id', $user->id)
            ->whereBetween('local_date', [$start->toDateString(), $local->toDateString()])
            ->sum('points_earned');
    }

    private function availableAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->addHours($this->settings->int('reward.hold_hours'));
    }
}
