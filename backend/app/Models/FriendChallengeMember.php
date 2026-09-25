<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FriendChallengeMember extends Model
{
    public const INVITED = 'invited';

    public const JOINED = 'joined';

    public const LEFT = 'left';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(FriendChallenge::class, 'friend_challenge_id');
    }
}
