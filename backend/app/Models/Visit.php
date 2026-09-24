<?php

namespace App\Models;

use App\Enums\VisitStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['id', 'last_lat', 'last_lng', 'qr_token_hash'];

    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'entered_at' => 'datetime',
            'last_ping_at' => 'datetime',
            'last_inside_at' => 'datetime',
            'qr_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'stay_seconds' => 'integer',
            'pings_count' => 'integer',
            'outside_pings' => 'integer',
            'min_distance_m' => 'integer',
            'best_accuracy_m' => 'integer',
            'last_lat' => 'float',
            'last_lng' => 'float',
            'fraud_score' => 'integer',
            'points_awarded' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isOpen(): bool
    {
        return $this->status === VisitStatus::Started;
    }
}
