<?php

namespace Tests\Concerns;

use App\Domain\Activity\DailyActivityAggregator;
use App\Enums\SessionKind;
use App\Enums\SessionRewardStatus;
use App\Enums\SessionStatus;
use App\Models\Device;
use App\Models\User;
use App\Models\WalkingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/** Builds sessions directly in the DB (bypassing the API) to unit-test scoring and rewards. */
trait CreatesScoredSessions
{
    private int $seq = 0;

    /**
     * @param  list<array<string, mixed>>  $buckets  each: steps, duration_s?, activity_type?, accel_std?, accel_peak_hz?, detector_steps?, speed_mps?
     */
    protected function makeSession(User $user, Device $device, array $buckets, array $overrides = [], ?CarbonImmutable $start = null): WalkingSession
    {
        $start ??= CarbonImmutable::now()->subHours(2)->startOfMinute();
        $cursor = $start;
        $rows = [];
        foreach ($buckets as $b) {
            $duration = $b['duration_s'] ?? 60;
            $rows[] = array_merge(['started_at' => $cursor->toDateTimeString(), 'duration_s' => $duration, 'steps' => 0], $b);
            $cursor = $cursor->addSeconds($duration);
        }
        $raw = array_sum(array_column($rows, 'steps'));
        $detector = array_sum(array_map(fn ($r) => $r['detector_steps'] ?? 0, $rows));

        $session = WalkingSession::query()->create(array_merge([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'client_session_id' => (string) Str::uuid(),
            'sequence' => ++$this->seq,
            'payload_hash' => str_repeat('0', 64),
            'kind' => SessionKind::Active,
            'started_at' => $start,
            'ended_at' => $cursor,
            'local_date' => $start->setTimezone($user->timezone)->toDateString(),
            'raw_steps' => $raw,
            'duration_s' => $cursor->getTimestamp() - $start->getTimestamp(),
            'activity_type' => 'walking',
            'motion_summary' => ['detector_ratio' => $detector > 0 ? round($detector / max(1, $raw), 3) : null],
            'status' => SessionStatus::Submitted,
            'reward_status' => SessionRewardStatus::None,
        ], $overrides));
        $session->samples()->createMany($rows);
        app(DailyActivityAggregator::class)->refresh($user, $session->local_date->toDateString());

        return $session;
    }

    /** Realistic walking minutes: varying cadence, plausible accelerometer signature. */
    protected function walkingMinutes(int $minutes, int $base = 105): array
    {
        return array_map(fn ($i) => [
            'steps' => $base + ($i * 7) % 13 - 6,
            'detector_steps' => $base + ($i * 7) % 13 - 8,
            'accel_std' => 2.2,
            'accel_peak_hz' => 1.8,
            'activity_type' => 'walking',
        ], range(0, $minutes - 1));
    }
}
