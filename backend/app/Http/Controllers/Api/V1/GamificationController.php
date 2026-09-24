<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Gamification\AchievementService;
use App\Domain\Gamification\StreakService;
use App\Domain\Gamification\XpService;
use App\Domain\Leaderboard\LeaderboardService;
use App\Domain\Referral\ReferralService;
use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\UserAchievement;
use App\Models\UserStreak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GamificationController extends Controller
{
    public function progress(Request $request, XpService $xp, StreakService $streaks): JsonResponse
    {
        $user = $request->user();
        $streak = UserStreak::query()->find($user->id);

        return response()->json(['data' => [
            'level' => $xp->progress($user),
            'streak' => ['current' => $streak?->current_days ?? 0, 'longest' => $streak?->longest_days ?? 0, 'week' => $streaks->weekDots($user)],
        ]]);
    }

    public function achievements(Request $request, AchievementService $service): JsonResponse
    {
        $user = $request->user();
        $unlocked = UserAchievement::query()->where('user_id', $user->id)->pluck('unlocked_at', 'achievement_id');
        $metrics = $service->metrics($user);

        $items = Achievement::query()->where('is_active', true)->orderBy('sort')->get()->map(fn (Achievement $a) => [
            'key' => $a->key,
            'name' => $a->name,
            'description' => $a->description,
            'icon' => $a->icon,
            'threshold' => $a->threshold,
            'progress' => min($a->threshold, $metrics[$a->metric] ?? 0),
            'unlocked_at' => isset($unlocked[$a->id]) ? $unlocked[$a->id]->toIso8601String() : null,
            'xp_reward' => $a->xp_reward,
            'point_reward' => $a->point_reward,
        ]);

        return response()->json(['data' => $items]);
    }

    public function leaderboard(Request $request, LeaderboardService $boards): JsonResponse
    {
        $request->validate(['period' => ['sometimes', 'in:day,week,month']]);

        return response()->json(['data' => $boards->board($request->input('period', 'week'), $request->user())]);
    }

    public function referral(Request $request, ReferralService $referrals): JsonResponse
    {
        return response()->json(['data' => $referrals->summary($request->user())]);
    }
}
