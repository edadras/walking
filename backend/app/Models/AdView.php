<?php

namespace App\Models;

use App\Enums\AdViewStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A rewarded-ad view. Rewarded only via the internal timed token or a verified S2S callback. */
class AdView extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return ['status' => AdViewStatus::class, 'started_at' => 'datetime', 'completed_at' => 'datetime', 'points_awarded' => 'integer'];
    }

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AdProvider::class, 'ad_provider_id');
    }
}
