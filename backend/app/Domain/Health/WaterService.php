<?php

namespace App\Domain\Health;

use App\Domain\Settings\Settings;
use App\Exceptions\ApiException;
use App\Models\DailyActivity;
use App\Models\User;
use App\Models\WaterLog;
use Carbon\CarbonImmutable;

class WaterService
{
    public const MAX_DAILY_ML = 10000;

    public function __construct(private readonly Settings $settings) {}

    /** @return array<string, mixed> */
    public function day(User $user, ?string $date = null): array
    {
        $date ??= CarbonImmutable::now($user->timezone)->toDateString();
        $logs = WaterLog::query()->where('user_id', $user->id)->where('local_date', $date)->orderBy('logged_at')->get();
        $goal = $user->profile?->water_goal_ml ?? $this->settings->int('health.default_water_goal_ml');
        $glass = $this->settings->int('health.glass_ml');

        return [
            'date' => $date,
            'goal_ml' => $goal,
            'glass_ml' => $glass,
            'total_ml' => (int) $logs->sum('amount_ml'),
            'glasses' => round($logs->sum('amount_ml') / $glass, 1),
            'goal_glasses' => (int) ceil($goal / $glass),
            'suggested_goal_ml' => $this->suggestion($user, $date),
            'reminder_enabled' => (bool) $user->profile?->water_reminder_enabled,
            'reminder_interval_min' => $user->profile?->water_reminder_interval_min ?? 120,
            'logs' => $logs->map(fn (WaterLog $l) => ['id' => $l->id, 'amount_ml' => $l->amount_ml, 'logged_at' => $l->logged_at->toIso8601String()])->all(),
        ];
    }

    public function add(User $user, int $amountMl): WaterLog
    {
        $date = CarbonImmutable::now($user->timezone)->toDateString();
        $today = (int) WaterLog::query()->where('user_id', $user->id)->where('local_date', $date)->sum('amount_ml');
        if ($today + $amountMl > self::MAX_DAILY_ML) {
            throw ApiException::unprocessable('water_limit', 'مقدار ثبت‌شده برای امروز بیش از حد معمول است.');
        }

        return WaterLog::query()->create(['user_id' => $user->id, 'local_date' => $date, 'amount_ml' => $amountMl, 'logged_at' => now()]);
    }

    /**
     * General, non-medical guideline: ~33 ml per kg of body weight, plus a
     * little for active minutes. Shown as an approximate suggestion only.
     */
    public function suggestion(User $user, string $date): int
    {
        $weight = $user->profile?->weight_kg;
        if (! $weight) {
            return $this->settings->int('health.default_water_goal_ml');
        }
        $active = (int) DailyActivity::query()->where('user_id', $user->id)->where('local_date', $date)->value('active_minutes');
        $ml = $weight * 33 + min(90, $active) * 8;

        return (int) (round($ml / 250) * 250);
    }
}
