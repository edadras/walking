<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reward\RewardRules;
use App\Domain\Settings\Settings;
use App\Domain\Wallet\ConversionRate;
use App\Domain\Wallet\WalletSummary;
use App\Http\Controllers\Controller;
use App\Models\DailyActivity;
use App\Models\Reward;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reward Center: what the user earned today and exactly how to earn more.
 * Later phases append challenges, sponsor rewards and achievements.
 */
class RewardController extends Controller
{
    public function index(Request $request, RewardRules $rules, WalletSummary $wallet, ConversionRate $rates): JsonResponse
    {
        $user = $request->user();
        $local = CarbonImmutable::now($user->timezone);
        $today = DailyActivity::query()->where('user_id', $user->id)->where('local_date', $local->toDateString())->first();
        $rate = $rules->stepRate(now());
        $maxSteps = $rules->maxRewardedSteps(now());
        $dailyCap = $rules->dailyCap(now());
        $goal = $user->profile?->daily_step_goal ?? 7500;

        // Next 7 days of weekday multipliers so the user can plan ("Friday ×1.5").
        $upcoming = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $local->addDays($i)->setTime(12, 0);
            $m = $rules->multiplier($day);
            if ($m['factor'] > 1) {
                $upcoming[] = ['date' => $day->toDateString(), 'multiplier' => $m['factor'], 'names' => array_column($m['applied'], 'name')];
            }
        }

        return response()->json(['data' => [
            'today' => [
                'points' => $today?->points_earned ?? 0,
                'rial_value' => ($today?->points_earned ?? 0) * $rates->current(),
                'verified_steps' => $today?->verified_steps ?? 0,
                'rewarded_steps' => $today?->rewarded_steps ?? 0,
                'remaining_rewardable_steps' => max(0, $maxSteps - ($today?->rewarded_steps ?? 0)),
                'remaining_points' => max(0, $dailyCap - ($today?->points_earned ?? 0)),
                'goal' => $goal,
                'goal_reached' => $today?->goal_reached_at !== null,
                'cycling_distance_m' => $today?->cycling_distance_m ?? 0,
                'cycling_points' => $today?->cycling_points ?? 0,
            ],
            'wallet' => $wallet->for($user),
            'earning' => [
                'steps_per_unit' => $rate['steps'],
                'points_per_unit' => $rate['points'],
                'daily_cap' => $dailyCap,
                'weekly_cap' => $rules->weeklyCap(now()),
                'max_rewarded_steps' => $maxSteps,
                'cycling_points_per_km' => app(Settings::class)->int('cycling.points_per_km'),
                'cycling_daily_cap' => app(Settings::class)->int('cycling.daily_cap'),
                'goal_bonus' => $rules->goalBonus(now()),
                'streak_bonuses' => $rules->streakBonuses(now()),
                'multiplier_now' => $rules->multiplier($local)['factor'],
                'upcoming_multipliers' => $upcoming,
            ],
            'recent' => Reward::query()->where('user_id', $user->id)->latest('id')->limit(10)->get()->map(fn (Reward $r) => $this->reward($r)),
        ]]);
    }

    public function show(Request $request, Reward $reward): JsonResponse
    {
        abort_unless($reward->user_id === $request->user()->id, 404);

        return response()->json(['data' => $this->reward($reward, withBreakdown: true)]);
    }

    private function reward(Reward $r, bool $withBreakdown = false): array
    {
        return array_filter([
            'id' => $r->public_id,
            'kind' => $r->kind,
            'points' => $r->final_points,
            'base_points' => $r->base_points,
            'multiplier' => $r->multiplier,
            'capped_points' => $r->capped_points,
            'status' => $r->status,
            'created_at' => $r->created_at?->toIso8601String(),
            'breakdown' => $withBreakdown ? $r->breakdown : null,
        ], fn ($v) => $v !== null);
    }
}
