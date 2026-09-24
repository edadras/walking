<?php

namespace App\Models;

use App\Enums\AdFormat;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A creative. */
class Ad extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['id'];

    protected $attributes = ['is_active' => true, 'min_view_seconds' => 15];

    protected function casts(): array
    {
        return ['format' => AdFormat::class, 'is_active' => 'boolean', 'min_view_seconds' => 'integer'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }
}
