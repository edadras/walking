<?php

namespace App\Domain\Fraud\Data;

use App\Enums\SessionKind;
use App\Enums\SessionStatus;
use App\Models\ActivitySample;
use App\Models\Device;
use App\Models\User;
use App\Models\WalkingSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything a rule may look at, loaded once per session. Rules stay pure
 * functions of this context, which keeps them trivially unit-testable.
 */
final class SessionContext
{
    /** @param Collection<int, ActivitySample> $buckets */
    public function __construct(
        public readonly WalkingSession $session,
        public readonly Collection $buckets,
        public readonly Device $device,
        public readonly User $user,
        public readonly int $dailyVerifiedBefore,
        public readonly int $accountsOnDevice,
        public readonly int $activeDevicesForUser,
        public readonly int $recentRejections,
    ) {}

    public static function load(WalkingSession $session): self
    {
        $session->loadMissing(['samples', 'device', 'user']);

        $dailyVerified = (int) WalkingSession::query()
            ->where('user_id', $session->user_id)
            ->where('local_date', $session->local_date->toDateString())
            ->whereKeyNot($session->id)
            ->whereIn('status', [SessionStatus::Verified, SessionStatus::PartiallyVerified])
            ->sum('verified_steps');

        return new self(
            session: $session,
            buckets: $session->samples->values(),
            device: $session->device,
            user: $session->user,
            dailyVerifiedBefore: $dailyVerified,
            accountsOnDevice: DB::table('device_user_links')->where('device_id', $session->device_id)->where('last_seen_at', '>=', now()->subDays(30))->count(),
            activeDevicesForUser: DB::table('device_user_links')->where('user_id', $session->user_id)->where('last_seen_at', '>=', now()->subDay())->count(),
            recentRejections: WalkingSession::query()->where('user_id', $session->user_id)->where('status', SessionStatus::Rejected)->where('created_at', '>=', now()->subDays(7))->count(),
        );
    }

    public function isActive(): bool
    {
        return $this->session->kind === SessionKind::Active;
    }

    public function motion(string $key, mixed $default = null): mixed
    {
        return $this->session->motion_summary[$key] ?? $default;
    }

    public function gps(string $key, mixed $default = null): mixed
    {
        return $this->session->gps_summary[$key] ?? $default;
    }

    /** Steps per minute inside a bucket. */
    public static function cadence(ActivitySample $b): float
    {
        return $b->duration_s > 0 ? $b->steps / ($b->duration_s / 60) : 0;
    }
}
