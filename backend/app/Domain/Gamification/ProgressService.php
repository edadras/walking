<?php

namespace App\Domain\Gamification;

use App\Domain\Challenge\ChallengeService;
use App\Domain\Leaderboard\LeaderboardService;
use App\Domain\Referral\ReferralService;
use App\Domain\Reward\RewardRules;
use App\Domain\Settings\Settings;
use App\Domain\Wallet\WalletService;
use App\Enums\SessionStatus;
use App\Enums\TransactionType;
use App\Models\DailyActivity;
use App\Models\Reward;
use App\Models\User;
use App\Models\WalkingSession;
use App\Notifications\UserNotification;
use Illuminate\Support\Facades\Cache;

/** Everything that follows a scored session: streak, XP, badges, records, boards, challenges, referral. */
class ProgressService
{
    public function __construct(
        private readonly StreakService $streaks,
        private readonly XpService $xp,
        private readonly AchievementService $achievements,
        private readonly PersonalRecordService $records,
        private readonly LeaderboardService $leaderboard,
        private readonly ChallengeService $challenges,
        private readonly ReferralService $referrals,
        private readonly RewardRules $rules,
        private readonly WalletService $wallet,
        private readonly Settings $settings,
    ) {}

    public function afterSession(WalkingSession $session): void
    {
        $session->loadMissing('user.profile');
        $user = $session->user;
        $date = $session->local_date->toDateString();

        if (in_array($session->status, [SessionStatus::Verified, SessionStatus::PartiallyVerified], true) && $session->verified_steps > 0) {
            $this->xp->award($user, intdiv($session->verified_steps, max(1, $this->settings->int('gamification.steps_per_xp'))), 'steps', 'session:'.$session->id);
        }

        $daily = DailyActivity::query()->where('user_id', $user->id)->where('local_date', $date)->first();
        if ($daily?->goal_reached_at !== null) {
            if ($this->xp->award($user, $this->settings->int('gamification.goal_xp'), 'goal', 'goal:'.$date)) {
                $user->notify(new UserNotification('daily_goal', 'هدف امروز کامل شد', 'آفرین! به هدف '.number_format($daily->goal_steps).' قدم امروز رسیدی.', ['type' => 'goal']));
            }
        }

        $streak = $this->streaks->refresh($user);
        $this->streakBonus($user, $streak->current_days, $streak->last_goal_date?->toDateString());
        $this->achievements->evaluate($user);
        $this->records->refresh($user);
        $this->leaderboard->sync($user, $date);
        $this->challenges->refreshFor($user);
        $this->referrals->evaluate($user);

        Cache::forget('home:v1:'.$user->id);
    }

    private function streakBonus(User $user, int $days, ?string $lastGoalDate): void
    {
        $points = $this->rules->streakBonuses(now())[$days] ?? 0;
        if ($points <= 0 || $lastGoalDate === null) {
            return;
        }
        $key = 'streak:'.$days.':'.$lastGoalDate;
        if (Reward::query()->where('user_id', $user->id)->where('kind', 'streak_bonus')->where('source_id', $user->id)->whereJsonContains('breakdown->key', $key)->exists()) {
            return;
        }
        $t = $this->wallet->hold($user, $points, TransactionType::StreakBonus, $key, "پاداش {$days} روز متوالی", $user);
        Reward::query()->create([
            'user_id' => $user->id, 'kind' => 'streak_bonus', 'source_type' => $user->getMorphClass(), 'source_id' => $user->id,
            'bonus_points' => $points, 'final_points' => $points, 'status' => 'pending', 'breakdown' => ['key' => $key, 'days' => $days],
            'point_transaction_id' => $t->id,
        ]);
        $user->notify(new UserNotification('reward_received', "{$days} روز متوالی!", "پاداش {$points} امتیازی زنجیره روزهایت در راه است.", ['type' => 'streak']));
    }
}
