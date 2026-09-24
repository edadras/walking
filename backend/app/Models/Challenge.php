<?php

namespace App\Models;

use App\Enums\ChallengeStatus;
use App\Enums\ChallengeType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Challenge extends Model
{
    use HasUlids;

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['id'];

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
            'type' => ChallengeType::class,
            'status' => ChallengeStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'join_until' => 'datetime',
            'target_value' => 'integer',
            'reward_points' => 'integer',
            'reward_xp' => 'integer',
            'participants_count' => 'integer',
            'max_participants' => 'integer',
        ];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ChallengeParticipant::class);
    }

    public function isRunning(): bool
    {
        return $this->status === ChallengeStatus::Active && $this->starts_at->isPast() && $this->ends_at->isFuture();
    }

    public function isJoinable(): bool
    {
        return $this->status === ChallengeStatus::Active
            && $this->ends_at->isFuture()
            && ($this->join_until === null || $this->join_until->isFuture())
            && ($this->max_participants === null || $this->participants_count < $this->max_participants);
    }
}
