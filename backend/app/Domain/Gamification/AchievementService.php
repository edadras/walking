<?php

namespace App\Domain\Gamification;

use App\Domain\Wallet\WalletService;
use App\Enums\TransactionType;
use App\Models\Achievement;
use App\Models\ChallengeParticipant;
use App\Models\DailyActivity;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserStreak;
use App\Notifications\UserNotification;
use Illuminate\Support\Collection;

/** Unlocks badges when a verified metric crosses its threshold. */
class AchievementService
{
    public const METRICS = [
        'total_steps' => 'مجموع قدم‌های تأییدشده',
        'daily_steps' => 'بیشترین قدم در یک روز',
        'streak_days' => 'روزهای متوالی',
        'total_distance_m' => 'مجموع مسافت (متر)',
        'active_days' => 'روزهای فعال',
        'challenges_completed' => 'چالش‌های تکمیل‌شده',
    ];

    public function __construct(private readonly XpService $xp, private readonly WalletService $wallet) {}

    /** @return Collection<int, Achievement> newly unlocked */
    public function evaluate(User $user): Collection
    {
        $unlocked = UserAchievement::query()->where('user_id', $user->id)->pluck('achievement_id')->all();
        $candidates = Achievement::query()->where('is_active', true)->whereNotIn('id', $unlocked)->get();
        if ($candidates->isEmpty()) {
            return collect();
        }

        $metrics = $this->metrics($user);
        $new = collect();
        foreach ($candidates as $achievement) {
            if (($metrics[$achievement->metric] ?? 0) < $achievement->threshold) {
                continue;
            }
            $row = UserAchievement::query()->firstOrCreate(
                ['user_id' => $user->id, 'achievement_id' => $achievement->id],
                ['unlocked_at' => now()],
            );
            if (! $row->wasRecentlyCreated) {
                continue;
            }
            $this->xp->award($user, $achievement->xp_reward, 'achievement', 'achievement:'.$achievement->id);
            if ($achievement->point_reward > 0) {
                $this->wallet->hold($user, $achievement->point_reward, TransactionType::AchievementReward, 'achievement:'.$achievement->id, 'دستاورد: '.$achievement->name, $achievement);
            }
            $user->notify(new UserNotification('reward_received', 'دستاورد جدید: '.$achievement->name, $achievement->description, ['type' => 'achievement', 'key' => $achievement->key]));
            $new->push($achievement);
        }

        return $new;
    }

    /** @return array<string, int> */
    public function metrics(User $user): array
    {
        $daily = DailyActivity::query()->where('user_id', $user->id)
            ->selectRaw('COALESCE(SUM(verified_steps),0) total, COALESCE(MAX(verified_steps),0) best, SUM(CASE WHEN verified_steps > 0 THEN 1 ELSE 0 END) active_days')
            ->first();
        $distance = (int) $user->walkingSessions()->whereIn('status', ['verified', 'partially_verified'])
            ->selectRaw('COALESCE(SUM(CASE WHEN raw_steps > 0 THEN distance_m * verified_steps / raw_steps ELSE 0 END),0) d')->value('d');

        return [
            'total_steps' => (int) $daily->total,
            'daily_steps' => (int) $daily->best,
            'active_days' => (int) $daily->active_days,
            'total_distance_m' => $distance,
            'streak_days' => (int) (UserStreak::query()->find($user->id)?->longest_days ?? 0),
            'challenges_completed' => ChallengeParticipant::query()->where('user_id', $user->id)->whereNotNull('completed_at')->count(),
        ];
    }
}
