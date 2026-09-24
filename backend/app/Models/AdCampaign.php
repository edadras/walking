<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdCampaign extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id', 'impressions_count', 'clicks_count', 'points_spent', 'rewards_count'];

    protected $hidden = ['id'];

    protected $attributes = ['impressions_count' => 0, 'clicks_count' => 0, 'points_spent' => 0, 'rewards_count' => 0, 'reward_points' => 0, 'point_budget' => 0, 'priority' => 10];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'targeting' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'approved_at' => 'datetime',
            'priority' => 'integer',
            'impression_limit' => 'integer',
            'click_limit' => 'integer',
            'impressions_count' => 'integer',
            'clicks_count' => 'integer',
            'frequency_cap_per_day' => 'integer',
            'reward_points' => 'integer',
            'point_budget' => 'integer',
            'points_spent' => 'integer',
            'rewards_count' => 'integer',
        ];
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function placements(): BelongsToMany
    {
        return $this->belongsToMany(AdPlacement::class, 'ad_campaign_placement');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    public function ctr(): float
    {
        return $this->impressions_count === 0 ? 0 : round($this->clicks_count * 100 / $this->impressions_count, 2);
    }

    public function targets(User $user): bool
    {
        $t = $this->targeting ?? [];

        return (empty($t['min_level']) || $user->level >= (int) $t['min_level'])
            && (empty($t['max_level']) || $user->level <= (int) $t['max_level']);
    }
}
