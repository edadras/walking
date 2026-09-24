<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Enums\SessionKind;
use App\Enums\SessionRewardStatus;
use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalkingSession extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['id', 'user_id', 'device_id', 'payload_hash'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected function casts(): array
    {
        return [
            'kind' => SessionKind::class,
            'status' => SessionStatus::class,
            'reward_status' => SessionRewardStatus::class,
            'activity_type' => ActivityType::class,
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'local_date' => 'immutable_date',
            'scored_at' => 'datetime',
            'gps_summary' => 'json',
            'motion_summary' => 'json',
            'raw_steps' => 'integer',
            'verified_steps' => 'integer',
            'distance_m' => 'integer',
            'duration_s' => 'integer',
            'active_duration_s' => 'integer',
            'calories_kcal' => 'float',
            'overlap_s' => 'integer',
            'sequence' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function samples(): HasMany
    {
        return $this->hasMany(ActivitySample::class)->orderBy('started_at');
    }

    /** Steps shown to the user: verified once scored, otherwise the raw claim. */
    public function displaySteps(): int
    {
        return $this->verified_steps ?? $this->raw_steps;
    }
}
