<?php

namespace App\Domain\Sponsor;

use App\Domain\Challenge\ChallengeService;
use App\Domain\Settings\FeatureFlags;
use App\Domain\Settings\Settings;
use App\Domain\Wallet\WalletService;
use App\Enums\LocationStatus;
use App\Enums\TransactionType;
use App\Enums\VisitStatus;
use App\Exceptions\ApiException;
use App\Models\Campaign;
use App\Models\Device;
use App\Models\FraudEvent;
use App\Models\Location;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\UserNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Sponsored location visit (docs/phase-0/05-flows.md §5.6).
 *
 * The client only reports positions; the SERVER decides presence and measures
 * the stay with its own clock. A visit is rewarded when:
 *   inside geofence (radius + bounded GPS error) for min_stay_seconds of
 *   consecutive, closely spaced pings, AND (if required) a valid rotating QR
 *   of that branch was scanned while present,
 * and the campaign/sponsor budget and per-user limits still allow it.
 */
class VisitService
{
    public function __construct(
        private readonly Settings $settings,
        private readonly FeatureFlags $flags,
        private readonly QrToken $qr,
        private readonly SponsorBudget $budget,
        private readonly WalletService $wallet,
        private readonly CouponService $coupons,
        private readonly ChallengeService $challenges,
    ) {}

    /** @param array{lat: float, lng: float, accuracy: float, mock?: bool} $fix */
    public function start(User $user, ?Device $device, Campaign $campaign, Location $location, array $fix): Visit
    {
        if (! $this->flags->enabled('sponsored_locations', $user->id)) {
            throw ApiException::forbidden('feature_disabled', 'این بخش در حال حاضر فعال نیست.');
        }
        $this->assertOffer($campaign, $location);

        $open = Visit::query()->where('user_id', $user->id)->where('status', VisitStatus::Started)->get();
        $same = $open->first(fn (Visit $v) => $v->campaign_id === $campaign->id && $v->location_id === $location->id);
        if ($same !== null) {
            return $this->ping($same, $fix);
        }

        if (! empty($fix['mock'])) {
            $this->fraud($user, $device, null, 'visit_mock_location', 90, 'high', ['campaign' => $campaign->public_id]);
            throw ApiException::unprocessable('mock_location', 'موقعیت مکانی شبیه‌سازی‌شده قابل قبول نیست.');
        }
        $this->assertAccuracy($fix);
        $distance = $this->distance($location, $fix);
        if (! $this->inside($location, $distance, $fix['accuracy'])) {
            throw ApiException::unprocessable('outside_geofence', 'هنوز به محل نرسیده‌ای.', ['distance_m' => (int) round($distance)]);
        }
        $this->assertEligible($user, $campaign, $location);

        // One open visit at a time: starting a new one closes the others.
        Visit::query()->whereIn('id', $open->pluck('id'))->update(['status' => VisitStatus::Expired, 'last_lat' => null, 'last_lng' => null]);

        $now = now();
        $visit = Visit::query()->create([
            'user_id' => $user->id,
            'device_id' => $device?->id,
            'campaign_id' => $campaign->id,
            'location_id' => $location->id,
            'status' => VisitStatus::Started,
            'entered_at' => $now,
            'last_ping_at' => $now,
            'last_inside_at' => $now,
            'pings_count' => 1,
            'min_distance_m' => (int) round($distance),
            'best_accuracy_m' => (int) round($fix['accuracy']),
            'last_lat' => $fix['lat'],
            'last_lng' => $fix['lng'],
        ]);

        return $this->evaluate($visit->setRelation('campaign', $campaign)->setRelation('location', $location));
    }

    /** @param array{lat: float, lng: float, accuracy: float, mock?: bool} $fix */
    public function ping(Visit $visit, array $fix): Visit
    {
        if (! $visit->isOpen()) {
            return $visit;
        }
        $visit->loadMissing(['campaign', 'location']);
        $now = now();
        $gap = max(0, $visit->last_ping_at->diffInSeconds($now, true));

        // Pings faster than half the interval add nothing (and cannot be used to spam).
        if ($gap < max(1, intdiv($this->settings->int('visits.ping_interval_s'), 2))) {
            return $visit;
        }
        if (! empty($fix['mock'])) {
            return $this->reject($visit, 'mock_location', 90, ['during' => 'ping']);
        }

        $distance = $this->distance($visit->location, $fix);
        if ($visit->last_lat !== null && $gap > 0) {
            $moved = Geo::distanceM($visit->last_lat, $visit->last_lng, $fix['lat'], $fix['lng']);
            if ($moved / $gap > $this->settings->int('visits.max_speed_mps') && $moved > 200) {
                return $this->reject($visit, 'teleport', 80, ['moved_m' => (int) $moved, 'seconds' => $gap]);
            }
        }

        $accurate = $fix['accuracy'] <= $this->settings->int('visits.max_accuracy_m');
        $inside = $accurate && $this->inside($visit->location, $distance, $fix['accuracy']);
        $wasInside = $visit->last_inside_at !== null && $visit->last_inside_at->equalTo($visit->last_ping_at);
        $credit = ($inside && $wasInside && $gap <= $this->settings->int('visits.max_ping_gap_s')) ? $gap : 0;

        $visit->forceFill([
            'last_ping_at' => $now,
            'last_inside_at' => $inside ? $now : $visit->last_inside_at,
            'stay_seconds' => $visit->stay_seconds + $credit,
            'pings_count' => $visit->pings_count + 1,
            'outside_pings' => $visit->outside_pings + ($inside ? 0 : 1),
            'min_distance_m' => min($visit->min_distance_m ?? PHP_INT_MAX, (int) round($distance)),
            'best_accuracy_m' => min($visit->best_accuracy_m ?? PHP_INT_MAX, (int) round($fix['accuracy'])),
            'last_lat' => $fix['lat'],
            'last_lng' => $fix['lng'],
        ])->save();

        return $this->evaluate($visit);
    }

    public function submitQr(Visit $visit, string $token): Visit
    {
        if (! $visit->isOpen()) {
            throw ApiException::conflict('visit_closed', 'این بازدید دیگر فعال نیست.');
        }
        $visit->loadMissing(['campaign', 'location']);

        $error = $this->qr->verify($token, $visit->location);
        if ($error !== null) {
            if ($error === 'qr_invalid' || $error === 'qr_wrong_location') {
                $this->fraud($visit->user, null, $visit, 'visit_bad_qr', 30, 'medium', ['reason' => $error]);
            }
            throw ApiException::unprocessable($error, match ($error) {
                'qr_expired' => 'این کد منقضی شده. کد روی صفحه شعبه را دوباره اسکن کن.',
                'qr_wrong_location' => 'این کد متعلق به این شعبه نیست.',
                default => 'کد QR معتبر نیست.',
            });
        }

        $present = $visit->last_inside_at !== null
            && $visit->last_inside_at->equalTo($visit->last_ping_at)
            && $visit->last_ping_at->diffInSeconds(now(), true) <= $this->settings->int('visits.max_ping_gap_s');
        if (! $present) {
            throw ApiException::unprocessable('not_present', 'برای اسکن باید داخل محدوده شعبه باشی.');
        }

        try {
            $visit->forceFill(['qr_token_hash' => hash('sha256', $token), 'qr_verified_at' => now()])->save();
        } catch (UniqueConstraintViolationException) {
            throw ApiException::conflict('qr_used', 'این کد را قبلاً استفاده کرده‌ای. صبر کن کد جدید نمایش داده شود.');
        }

        return $this->evaluate($visit);
    }

    /** Scheduled: visits that stopped pinging are closed and their last position erased. */
    public function expireStale(): int
    {
        return Visit::query()->where('status', VisitStatus::Started)
            ->where('last_ping_at', '<', now()->subMinutes($this->settings->int('visits.expire_minutes')))
            ->update(['status' => VisitStatus::Expired, 'last_lat' => null, 'last_lng' => null, 'updated_at' => now()]);
    }

    /** Can this user still earn at this location? Returns a reason code or null. */
    public function ineligibility(User $user, Campaign $campaign, Location $location): ?string
    {
        $rewarded = Visit::query()->where('user_id', $user->id)->where('campaign_id', $campaign->id)->where('status', VisitStatus::Rewarded);
        if ((clone $rewarded)->count() >= $campaign->max_rewards_per_user) {
            return 'limit_reached';
        }
        if ($campaign->cooldown_hours > 0 && (clone $rewarded)->where('location_id', $location->id)->where('verified_at', '>', now()->subHours($campaign->cooldown_hours))->exists()) {
            return 'cooldown';
        }
        if (($campaign->total_limit !== null && $campaign->rewards_count >= $campaign->total_limit)
            || $campaign->budgetRemaining() < $campaign->reward_points
            || $campaign->sponsor->budgetRemaining() < $campaign->reward_points) {
            return 'campaign_exhausted';
        }

        return null;
    }

    private function evaluate(Visit $visit): Visit
    {
        $c = $visit->campaign;
        $stayed = $visit->stay_seconds >= $c->min_stay_seconds;
        $qrOk = ! $c->requiresQr() || $visit->qr_verified_at !== null;

        return ($stayed && $qrOk) ? $this->reward($visit) : $visit;
    }

    private function reward(Visit $visit): Visit
    {
        $result = DB::transaction(function () use ($visit) {
            // Serialises all rewards of this campaign: limits and budget are checked under the lock.
            $campaign = Campaign::query()->whereKey($visit->campaign_id)->with('sponsor')->lockForUpdate()->firstOrFail();
            $locked = Visit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isOpen()) {
                return $locked;
            }
            $user = $locked->user;

            $reason = ! $campaign->isRunning() ? 'campaign_ended' : $this->ineligibility($user, $campaign, $visit->location);
            if ($reason === null && ! $this->budget->consume($campaign, $campaign->reward_points)) {
                $reason = 'campaign_exhausted';
            }
            if ($reason !== null) {
                $locked->forceFill(['status' => VisitStatus::Rejected, 'rejection_reason' => $reason, 'verified_at' => now(), 'last_lat' => null, 'last_lng' => null])->save();

                return $locked;
            }

            $tx = null;
            if ($campaign->reward_points > 0) {
                $tx = $this->wallet->hold($user, $campaign->reward_points, TransactionType::SponsorReward, 'visit:'.$locked->id,
                    'پاداش بازدید: '.$campaign->sponsor->name, $locked, now()->addHours($this->settings->int('reward.hold_hours')));
            }
            if ($campaign->coupon_id !== null) {
                $this->coupons->issue($user, $campaign->coupon, 'visit', $locked->id, 'visit:'.$locked->id);
            }
            $locked->forceFill([
                'status' => VisitStatus::Rewarded,
                'verified_at' => now(),
                'points_awarded' => $campaign->reward_points,
                'point_transaction_id' => $tx?->id,
                'last_lat' => null,
                'last_lng' => null,
            ])->save();

            return $locked;
        });

        if ($result->status === VisitStatus::Rewarded) {
            $this->challenges->refreshFor($result->user);
            $result->user->notify(new UserNotification('reward_received', 'بازدید تأیید شد', 'پاداش بازدیدت از '.$visit->location->name.' ثبت شد.', ['type' => 'visit', 'id' => $result->public_id]));
        }

        return $result->setRelation('campaign', $visit->campaign)->setRelation('location', $visit->location);
    }

    private function reject(Visit $visit, string $reason, int $score, array $details): Visit
    {
        $visit->forceFill(['status' => VisitStatus::Rejected, 'rejection_reason' => $reason, 'fraud_score' => $score, 'last_lat' => null, 'last_lng' => null])->save();
        $this->fraud($visit->user, null, $visit, 'visit_'.$reason, $score, $score >= 80 ? 'high' : 'medium', $details);

        return $visit;
    }

    private function assertOffer(Campaign $campaign, Location $location): void
    {
        $offered = $campaign->isRunning()
            && $campaign->sponsor?->isApproved()
            && $location->status === LocationStatus::Approved
            && $location->sponsor_id === $campaign->sponsor_id
            && $campaign->locations()->whereKey($location->id)->exists();
        if (! $offered) {
            throw ApiException::unprocessable('campaign_unavailable', 'این پیشنهاد در حال حاضر فعال نیست.');
        }
        if (! $location->isOpenAt(now())) {
            throw ApiException::unprocessable('location_closed', 'این شعبه الان باز نیست.');
        }
    }

    private function assertAccuracy(array $fix): void
    {
        if ($fix['accuracy'] > $this->settings->int('visits.max_accuracy_m')) {
            throw ApiException::unprocessable('poor_accuracy', 'دقت موقعیت کافی نیست. کمی صبر کن یا به فضای باز برو.', ['accuracy_m' => (int) $fix['accuracy']]);
        }
    }

    private function assertEligible(User $user, Campaign $campaign, Location $location): void
    {
        $reason = $this->ineligibility($user, $campaign, $location);
        if ($reason !== null) {
            throw ApiException::conflict($reason, match ($reason) {
                'limit_reached' => 'پاداش این کمپین را قبلاً گرفته‌ای.',
                'cooldown' => 'به‌تازگی از این شعبه پاداش گرفته‌ای. بعداً دوباره سر بزن.',
                default => 'ظرفیت این کمپین تمام شده است.',
            });
        }
    }

    private function distance(Location $location, array $fix): float
    {
        return Geo::distanceM($location->latitude, $location->longitude, (float) $fix['lat'], (float) $fix['lng']);
    }

    /** GPS error helps only up to a small allowance, so a poor fix cannot stretch the fence. */
    private function inside(Location $location, float $distance, float $accuracy): bool
    {
        return $distance <= $location->radius_m + min($accuracy, $this->settings->int('visits.accuracy_allowance_m'));
    }

    private function fraud(User $user, ?Device $device, ?Visit $visit, string $rule, int $score, string $severity, array $details): void
    {
        FraudEvent::query()->create([
            'user_id' => $user->id,
            'device_id' => $device?->id ?? $visit?->device_id,
            'subject_type' => $visit?->getMorphClass() ?? 'visit',
            'subject_id' => $visit?->id ?? 0,
            'rule_key' => $rule,
            'score' => $score,
            'severity' => $severity,
            'details' => $details,
        ]);
    }
}
