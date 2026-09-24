<?php

namespace App\Models;

use App\Enums\UserCouponStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCoupon extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return [
            'status' => UserCouponStatus::class,
            'claimed_at' => 'datetime',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(SponsorUser::class, 'redeemed_by');
    }

    /** Status as the user should see it right now (expiry is applied lazily + by the scheduler). */
    public function effectiveStatus(): UserCouponStatus
    {
        if ($this->status === UserCouponStatus::Available && $this->expires_at?->isPast()) {
            return UserCouponStatus::Expired;
        }

        return $this->status;
    }
}
