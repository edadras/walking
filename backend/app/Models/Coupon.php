<?php

namespace App\Models;

use App\Enums\CouponStatus;
use App\Enums\DiscountType;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id', 'claimed_count', 'redeemed_count'];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return [
            'status' => CouponStatus::class,
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'min_purchase_rial' => 'integer',
            'point_cost' => 'integer',
            'claimable' => 'boolean',
            'valid_days' => 'integer',
            'expires_at' => 'datetime',
            'usage_limit' => 'integer',
            'per_user_limit' => 'integer',
            'claimed_count' => 'integer',
            'redeemed_count' => 'integer',
        ];
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function userCoupons(): HasMany
    {
        return $this->hasMany(UserCoupon::class);
    }

    public function isIssuable(): bool
    {
        return $this->status === CouponStatus::Active
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && ($this->usage_limit === null || $this->claimed_count < $this->usage_limit);
    }

    public function discountLabel(): string
    {
        return match ($this->discount_type) {
            DiscountType::Percent => $this->discount_value.'٪ تخفیف',
            DiscountType::Fixed => number_format($this->discount_value).' ریال تخفیف',
            DiscountType::FreeItem => 'رایگان',
        };
    }
}
