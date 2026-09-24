<?php

namespace App\Models;

use App\Enums\SponsorStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sponsor extends Model
{
    use HasPublicId, SoftDeletes;

    protected $guarded = ['id', 'public_id', 'point_budget', 'points_spent'];

    protected function casts(): array
    {
        return [
            'status' => SponsorStatus::class,
            'approved_at' => 'datetime',
            'point_budget' => 'integer',
            'points_spent' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(SponsorUser::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function isApproved(): bool
    {
        return $this->status === SponsorStatus::Approved;
    }

    public function budgetRemaining(): int
    {
        return max(0, $this->point_budget - $this->points_spent);
    }
}
