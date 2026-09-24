<?php

namespace App\Console\Commands;

use App\Models\DailyActivity;
use App\Models\User;
use App\Models\UserStreak;
use App\Notifications\UserNotification;
use Illuminate\Console\Command;

/** Evening nudge for users about to lose a streak of 2+ days. */
class SendStreakWarnings extends Command
{
    protected $signature = 'notifications:streak-warnings';

    protected $description = 'Warn users whose streak ends today unless they reach their goal';

    public function handle(): int
    {
        $today = now('Asia/Tehran')->toDateString();
        $sent = 0;
        UserStreak::query()->where('current_days', '>=', 2)->where('last_goal_date', '<', $today)->chunkById(500, function ($streaks) use ($today, &$sent) {
            foreach ($streaks as $streak) {
                $user = User::query()->find($streak->user_id);
                if ($user === null || ! $user->isActive()) {
                    continue;
                }
                $steps = (int) DailyActivity::query()->where('user_id', $user->id)->where('local_date', $today)->value('raw_steps');
                $user->notify(new UserNotification('streak_warning', "زنجیره {$streak->current_days} روزه‌ات در خطر است", 'هنوز فرصت داری به هدف امروز برسی. '.number_format(max(0, ($user->profile?->daily_step_goal ?? 7500) - $steps)).' قدم مانده.', ['type' => 'streak']));
                $sent++;
            }
        }, 'user_id');
        $this->info("Sent $sent");

        return self::SUCCESS;
    }
}
