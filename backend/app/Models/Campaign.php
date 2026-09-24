<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\VerificationMethod;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id', 'rewards_count', 'points_spent'];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'verification_method' => VerificationMethod::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'approved_at' => 'datetime',
            'min_stay_seconds' => 'integer',
            'reward_points' => 'integer',
            'max_rewards_per_user' => 'integer',
            'cooldown_hours' => 'integer',
            'total_limit' => 'integer',
            'rewards_count' => 'integer',
            'point_budget' => 'integer',
            'points_spent' => 'integer',
        ];
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function isRunning(): bool
    {
        return $this->status === CampaignStatus::Active && $this->starts_at->isPast() && $this->ends_at->isFuture();
    }

    public function budgetRemaining(): int
    {
        return max(0, $this->point_budget - $this->points_spent);
    }

    public function requiresQr(): bool
    {
        return $this->verification_method === VerificationMethod::GeofenceQr;
    }
}
