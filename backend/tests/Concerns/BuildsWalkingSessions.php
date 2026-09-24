<?php

namespace Tests\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

trait BuildsWalkingSessions
{
    protected int $nextSequence = 1;

    /**
     * An active session of `$minutes` one-minute buckets ending at `$end`
     * (default: now), `$perMinute` steps each.
     *
     * @return array<string, mixed>
     */
    protected function activeSession(int $minutes = 10, int $perMinute = 110, ?CarbonImmutable $end = null, array $overrides = []): array
    {
        $end ??= CarbonImmutable::now()->startOfMinute();
        $start = $end->subMinutes($minutes);

        $buckets = [];
        for ($i = 0; $i < $minutes; $i++) {
            $buckets[] = [
                'started_at' => $start->addMinutes($i)->toIso8601String(),
                'duration_s' => 60,
                'steps' => $perMinute,
                'detector_steps' => $perMinute - 2,
                'accel_std' => 2.4,
                'accel_peak_hz' => 1.8,
                'activity_type' => 'walking',
            ];
        }

        return array_replace([
            'client_session_id' => (string) Str::uuid(),
            'sequence' => $this->nextSequence++,
            'kind' => 'active',
            'started_at' => $start->toIso8601String(),
            'ended_at' => $end->toIso8601String(),
            'raw_steps' => $minutes * $perMinute,
            'buckets' => $buckets,
        ], $overrides);
    }

    /** A passive background window (one coarse bucket). */
    protected function passiveSession(int $steps, CarbonImmutable $start, int $minutes = 30): array
    {
        return [
            'client_session_id' => (string) Str::uuid(),
            'sequence' => $this->nextSequence++,
            'kind' => 'passive',
            'started_at' => $start->toIso8601String(),
            'ended_at' => $start->addMinutes($minutes)->toIso8601String(),
            'raw_steps' => $steps,
            'buckets' => [['started_at' => $start->toIso8601String(), 'duration_s' => $minutes * 60, 'steps' => $steps]],
        ];
    }
}
