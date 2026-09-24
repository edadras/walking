<?php

namespace App\Models;

use App\Enums\AdFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdPlacement extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['format' => AdFormat::class, 'is_active' => 'boolean'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AdProvider::class, 'ad_provider_id');
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(AdCampaign::class, 'ad_campaign_placement');
    }
}
