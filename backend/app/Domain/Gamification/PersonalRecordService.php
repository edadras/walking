<?php

namespace App\Domain\Gamification;

use App\Models\DailyActivity;
use App\Models\PersonalRecord;
use App\Models\User;
use Carbon\CarbonImmutable;

class PersonalRecordService
{
    /** @return array<string, array{value:int, date:?string}> */
    public function refresh(User $user): array
    {
        $bestDay = DailyActivity::query()->where('user_id', $user->id)->orderByDesc('verified_steps')->first(['local_date', 'verified_steps']);
        $bestSession = $user->walkingSessions()->whereNotNull('verified_steps')->orderByDesc('verified_steps')->first(['local_date', 'verified_steps']);

        // Best Iranian week (Sat–Fri) from daily rows of the last year.
        $weeks = [];
        DailyActivity::query()->where('user_id', $user->id)->where('local_date', '>=', now()->subYear()->toDateString())
            ->get(['local_date', 'verified_steps'])
            ->each(function ($d) use (&$weeks) {
                $date = CarbonImmutable::parse($d->local_date);
                $start = $date->subDays(($date->dayOfWeek + 1) % 7)->toDateString();
                $weeks[$start] = ($weeks[$start] ?? 0) + $d->verified_steps;
            });
        arsort($weeks);
        $bestWeekStart = array_key_first($weeks);

        $records = [
            'best_day_steps' => [(int) ($bestDay?->verified_steps ?? 0), $bestDay?->local_date?->toDateString()],
            'best_week_steps' => [(int) ($weeks[$bestWeekStart] ?? 0), $bestWeekStart],
            'best_session_steps' => [(int) ($bestSession?->verified_steps ?? 0), $bestSession?->local_date?->toDateString()],
        ];

        $out = [];
        foreach ($records as $metric => [$value, $date]) {
            $row = PersonalRecord::query()->firstOrNew(['user_id' => $user->id, 'metric' => $metric]);
            if (! $row->exists || $row->value !== $value) {
                $row->fill(['value' => $value, 'local_date' => $date, 'achieved_at' => now()])->save();
            }
            $out[$metric] = ['value' => $value, 'date' => $date];
        }

        return $out;
    }
}
