<?php

namespace App\Domain\Activity\Actions;

use App\Domain\Activity\ActivityEstimator;
use App\Domain\Activity\DailyActivityAggregator;
use App\Domain\Settings\Settings;
use App\Enums\SessionKind;
use App\Enums\SessionRewardStatus;
use App\Enums\SessionStatus;
use App\Events\WalkingSessionSubmitted;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\User;
use App\Models\WalkingSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Accepts one walking session from a device. Only structural and replay checks
 * happen here; plausibility (verified steps, fraud score) is the Fraud Engine's
 * job and runs asynchronously on WalkingSessionSubmitted.
 *
 * Guarantees:
 *  - idempotent on (device, client_session_id): the same payload returns the same session
 *  - strictly increasing per-device sequence (replays / re-ordering rejected)
 *  - bounded in time: not in the future, not older than the offline window,
 *    never crossing the user's local midnight (daily caps stay per-day)
 */
class SubmitWalkingSession
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityEstimator $estimator,
        private readonly DailyActivityAggregator $daily,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated payload (SubmitWalkingSessionRequest)
     * @return array{0: WalkingSession, 1: bool} session and whether it was newly created
     */
    public function handle(User $user, Device $device, array $data): array
    {
        $hash = self::payloadHash($data);

        if ($existing = $this->existing($device, $data['client_session_id'])) {
            return [$this->assertSamePayload($existing, $hash), false];
        }

        $kind = SessionKind::from($data['kind']);
        $start = CarbonImmutable::parse($data['started_at'])->utc();
        $end = CarbonImmutable::parse($data['ended_at'])->utc();
        $this->assertTimeWindow($kind, $start, $end);

        $localDate = $start->setTimezone($user->timezone)->toDateString();
        if ($end->subSecond()->setTimezone($user->timezone)->toDateString() !== $localDate) {
            throw ApiException::unprocessable('session_crosses_midnight', 'جلسه باید در یک روز تقویمی باشد.');
        }

        $buckets = $this->normalizeBuckets($kind, $data['buckets'], $start, $end, (int) $data['raw_steps']);

        try {
            $session = DB::transaction(function () use ($user, $device, $data, $kind, $start, $end, $localDate, $buckets, $hash) {
                // Serialises submissions per device: the sequence check and bump are atomic.
                $locked = Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
                if ((int) $data['sequence'] <= $locked->last_sequence) {
                    throw ApiException::conflict('sequence_replayed', 'این جلسه قبلاً ثبت شده یا ترتیب آن نامعتبر است.');
                }
                $locked->forceFill(['last_sequence' => (int) $data['sequence']])->save();

                $estimate = $this->estimator->estimate($buckets, $user->profile, $data['gps'] ?? null);

                $session = WalkingSession::query()->create([
                    'user_id' => $user->id,
                    'device_id' => $device->id,
                    'client_session_id' => $data['client_session_id'],
                    'sequence' => (int) $data['sequence'],
                    'payload_hash' => $hash,
                    'kind' => $kind,
                    'source' => $data['source'] ?? 'step_counter',
                    'started_at' => $start,
                    'ended_at' => $end,
                    'local_date' => $localDate,
                    'raw_steps' => (int) $data['raw_steps'],
                    'duration_s' => $end->getTimestamp() - $start->getTimestamp(),
                    'distance_m' => $estimate['distance_m'],
                    'active_duration_s' => $estimate['active_duration_s'],
                    'calories_kcal' => $estimate['calories_kcal'],
                    'activity_type' => $estimate['activity_type'],
                    'gps_summary' => $data['gps'] ?? null,
                    'motion_summary' => $this->motionSummary($data, $buckets),
                    'overlap_s' => $this->overlapSeconds($user, $start, $end),
                    'status' => SessionStatus::Submitted,
                    'reward_status' => SessionRewardStatus::None,
                ]);

                $session->samples()->createMany(array_map(fn (array $b) => [
                    ...$b,
                    'started_at' => $b['started_at']->toDateTimeString(),
                ], $buckets));

                $this->daily->refresh($user, $localDate);

                return $session;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request with the same client_session_id won the race.
            $existing = $this->existing($device, $data['client_session_id'])
                ?? throw ApiException::conflict('sequence_replayed', 'این جلسه قبلاً ثبت شده یا ترتیب آن نامعتبر است.');

            return [$this->assertSamePayload($existing, $hash), false];
        }

        WalkingSessionSubmitted::dispatch($session);

        return [$session, true];
    }

    /** Stable hash of the semantic payload (key order independent). */
    public static function payloadHash(array $data): string
    {
        $normalize = function ($v) use (&$normalize) {
            if (! is_array($v)) {
                return $v;
            }
            if (! array_is_list($v)) {
                ksort($v);
            }

            return array_map($normalize, $v);
        };

        return hash('sha256', json_encode($normalize($data), JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function existing(Device $device, string $clientSessionId): ?WalkingSession
    {
        return WalkingSession::query()->where('device_id', $device->id)->where('client_session_id', $clientSessionId)->first();
    }

    private function assertSamePayload(WalkingSession $existing, string $hash): WalkingSession
    {
        if (! hash_equals($existing->payload_hash, $hash)) {
            throw ApiException::conflict('session_conflict', 'جلسه‌ای با همین شناسه و اطلاعات متفاوت قبلاً ثبت شده است.');
        }

        return $existing;
    }

    private function assertTimeWindow(SessionKind $kind, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $now = CarbonImmutable::now();
        $maxDuration = $kind === SessionKind::Active
            ? $this->settings->int('activity.max_active_session_hours') * 3600
            : $this->settings->int('activity.max_passive_session_minutes') * 60;

        if ($end->lte($start) || $end->getTimestamp() - $start->getTimestamp() > $maxDuration) {
            throw ApiException::unprocessable('session_invalid', 'بازه زمانی جلسه معتبر نیست.');
        }
        if ($end->gt($now->addMinutes(2))) {
            throw ApiException::unprocessable('session_in_future', 'زمان جلسه با ساعت سرور هماهنگ نیست.');
        }
        if ($start->lt($now->subDays($this->settings->int('activity.offline_max_age_days')))) {
            throw ApiException::unprocessable('session_too_old', 'این جلسه قدیمی‌تر از حد مجاز برای ثبت است.');
        }
    }

    /**
     * Buckets must tile the session without overlapping, stay inside it, and
     * add up exactly to raw_steps. A hard physical ceiling rejects garbage early;
     * finer plausibility is left to the Fraud Engine.
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeBuckets(SessionKind $kind, array $raw, CarbonImmutable $start, CarbonImmutable $end, int $rawSteps): array
    {
        $buckets = [];
        foreach ($raw as $b) {
            $buckets[] = [
                'started_at' => CarbonImmutable::parse($b['started_at'])->utc(),
                'duration_s' => (int) $b['duration_s'],
                'steps' => (int) $b['steps'],
                'detector_steps' => isset($b['detector_steps']) ? (int) $b['detector_steps'] : null,
                'accel_std' => $b['accel_std'] ?? null,
                'accel_peak_hz' => $b['accel_peak_hz'] ?? null,
                'activity_type' => $b['activity_type'] ?? null,
                'activity_confidence' => $b['activity_confidence'] ?? null,
                'speed_mps' => $b['speed_mps'] ?? null,
                'gps_accuracy_m' => $b['gps_accuracy_m'] ?? null,
            ];
        }
        usort($buckets, fn ($a, $b) => $a['started_at'] <=> $b['started_at']);

        $maxBucket = $kind === SessionKind::Active ? 60 : 3600;
        $cursor = $start;
        $sum = 0;
        foreach ($buckets as $b) {
            $bucketEnd = $b['started_at']->addSeconds($b['duration_s']);
            $invalid = $b['duration_s'] < 1
                || $b['duration_s'] > $maxBucket
                || $b['started_at']->lt($cursor)
                || $bucketEnd->gt($end)
                || $b['steps'] > $b['duration_s'] * 6; // 360 steps/min: beyond any human
            if ($invalid) {
                throw ApiException::unprocessable('session_invalid', 'داده‌های جلسه معتبر نیست.');
            }
            $cursor = $bucketEnd;
            $sum += $b['steps'];
        }

        if ($sum !== $rawSteps) {
            throw ApiException::unprocessable('session_invalid', 'داده‌های جلسه معتبر نیست.');
        }

        return $buckets;
    }

    /** Total seconds this interval overlaps the user's existing sessions on any device. */
    private function overlapSeconds(User $user, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $overlap = 0;
        WalkingSession::query()
            ->where('user_id', $user->id)
            ->where('started_at', '<', $end)
            ->where('ended_at', '>', $start)
            ->get(['started_at', 'ended_at'])
            ->each(function (WalkingSession $s) use (&$overlap, $start, $end) {
                $overlap += min($end->getTimestamp(), $s->ended_at->getTimestamp()) - max($start->getTimestamp(), $s->started_at->getTimestamp());
            });

        return min($overlap, $end->getTimestamp() - $start->getTimestamp());
    }

    /** Server-derived motion features plus the client's raw hints (kept separate). */
    private function motionSummary(array $data, array $buckets): array
    {
        $moving = array_filter($buckets, fn ($b) => $b['steps'] > 0);
        $cadences = array_map(fn ($b) => $b['steps'] / ($b['duration_s'] / 60), $moving);
        sort($cadences);
        $detector = array_sum(array_map(fn ($b) => $b['detector_steps'] ?? 0, $buckets));
        $hasDetector = count(array_filter($buckets, fn ($b) => $b['detector_steps'] !== null)) > 0;

        return [
            'buckets' => count($buckets),
            'cadence_max' => $cadences === [] ? 0 : round(end($cadences), 1),
            'cadence_p95' => $cadences === [] ? 0 : round($cadences[(int) floor(0.95 * (count($cadences) - 1))], 1),
            'detector_ratio' => $hasDetector && $data['raw_steps'] > 0 ? round($detector / $data['raw_steps'], 3) : null,
            'transitions' => $data['motion']['transitions'] ?? [],
            'mock_location' => (bool) ($data['motion']['mock_location'] ?? false),
        ];
    }
}
