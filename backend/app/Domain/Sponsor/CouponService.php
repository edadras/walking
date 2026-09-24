<?php

namespace App\Domain\Sponsor;

use App\Domain\Audit\AuditLogger;
use App\Domain\Wallet\WalletService;
use App\Enums\TransactionType;
use App\Enums\UserCouponStatus;
use App\Exceptions\ApiException;
use App\Models\Coupon;
use App\Models\Location;
use App\Models\SponsorUser;
use App\Models\User;
use App\Models\UserCoupon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CouponService
{
    /** No 0/O/1/I/L — codes are read aloud and typed by cashiers. */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function __construct(private readonly WalletService $wallet, private readonly AuditLogger $audit) {}

    /**
     * Gives the user one coupon. Idempotent on (user, key). Returns null when the
     * coupon can no longer be issued (limit reached, expired, paused).
     */
    public function issue(User $user, Coupon $coupon, string $sourceType, ?int $sourceId, string $key): ?UserCoupon
    {
        $existing = UserCoupon::query()->where('user_id', $user->id)->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $coupon, $sourceType, $sourceId, $key) {
            $locked = Coupon::query()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isIssuable() || $this->heldBy($user, $locked) >= $locked->per_user_limit) {
                return null;
            }
            $locked->increment('claimed_count');

            $expires = now()->addDays(max(1, $locked->valid_days));
            if ($locked->expires_at !== null && $locked->expires_at->lt($expires)) {
                $expires = $locked->expires_at;
            }

            return UserCoupon::query()->create([
                'coupon_id' => $locked->id,
                'user_id' => $user->id,
                'code' => $this->uniqueCode(),
                'status' => UserCouponStatus::Available,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'idempotency_key' => $key,
                'claimed_at' => now(),
                'expires_at' => $expires,
            ]);
        });
    }

    /** Buys a claimable coupon with points: the debit and the coupon commit together or not at all. */
    public function claim(User $user, Coupon $coupon, string $idempotencyKey): UserCoupon
    {
        if (! $coupon->claimable || $coupon->sponsor?->isApproved() === false) {
            throw ApiException::unprocessable('coupon_not_claimable', 'این کوپن قابل دریافت نیست.');
        }
        $key = 'claim:'.$idempotencyKey;

        return DB::transaction(function () use ($user, $coupon, $key) {
            $userCoupon = $this->issue($user, $coupon, 'claim', null, $key);
            if ($userCoupon === null) {
                throw ApiException::conflict('coupon_unavailable', 'این کوپن تمام شده یا به سقف دریافت خود رسیده‌ای.');
            }
            if ($coupon->point_cost > 0 && $userCoupon->wasRecentlyCreated) {
                $this->wallet->debit($user, $coupon->point_cost, TransactionType::Purchase, 'coupon:'.$userCoupon->public_id, 'دریافت کوپن: '.$coupon->title, $userCoupon);
            }

            return $userCoupon;
        });
    }

    /**
     * Cashier redemption. Only coupons of the cashier's own sponsor; each code
     * works once (conditional UPDATE, safe under concurrent scans).
     */
    public function redeem(SponsorUser $cashier, string $code, ?Location $location = null): UserCoupon
    {
        $code = strtoupper(preg_replace('/[\s-]/', '', $code));
        $userCoupon = UserCoupon::query()->where('code', $code)->with('coupon')->first();

        if ($userCoupon === null || $userCoupon->coupon->sponsor_id !== $cashier->sponsor_id) {
            throw ApiException::unprocessable('coupon_not_found', 'کوپنی با این کد برای مجموعه شما پیدا نشد.');
        }
        if ($location !== null && $location->sponsor_id !== $cashier->sponsor_id) {
            throw ApiException::forbidden('forbidden', 'این شعبه متعلق به مجموعه شما نیست.');
        }
        if ($userCoupon->effectiveStatus() !== UserCouponStatus::Available) {
            throw ApiException::conflict('coupon_'.$userCoupon->effectiveStatus()->value, 'این کوپن '.$userCoupon->effectiveStatus()->label().' است.');
        }

        $updated = DB::transaction(function () use ($userCoupon, $cashier, $location) {
            $n = UserCoupon::query()->whereKey($userCoupon->id)->where('status', UserCouponStatus::Available)
                ->update(['status' => UserCouponStatus::Used, 'used_at' => now(), 'redeemed_by' => $cashier->id, 'redeemed_location_id' => $location?->id]);
            if ($n === 1) {
                Coupon::query()->whereKey($userCoupon->coupon_id)->increment('redeemed_count');
            }

            return $n;
        });
        if ($updated === 0) {
            throw ApiException::conflict('coupon_used', 'این کوپن قبلاً استفاده شده است.');
        }
        $this->audit->log('coupon.redeemed', $userCoupon, meta: ['code' => $code, 'location_id' => $location?->id], actor: $cashier);

        return $userCoupon->fresh(['coupon']);
    }

    /** Scheduled: marks passed-expiry coupons as expired. */
    public function expire(): int
    {
        return UserCoupon::query()->where('status', UserCouponStatus::Available)->where('expires_at', '<', now())
            ->update(['status' => UserCouponStatus::Expired]);
    }

    private function heldBy(User $user, Coupon $coupon): int
    {
        return UserCoupon::query()->where('user_id', $user->id)->where('coupon_id', $coupon->id)->where('status', '!=', UserCouponStatus::Revoked)->count();
    }

    private function uniqueCode(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $code = '';
            for ($j = 0; $j < 8; $j++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            if (! UserCoupon::query()->where('code', $code)->exists()) {
                return $code;
            }
        }
        throw new RuntimeException('Could not generate a unique coupon code.');
    }
}
