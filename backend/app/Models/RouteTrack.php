<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteTrack extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['id', 'user_id', 'walking_session_id'];

    protected function casts(): array
    {
        return [
            'points' => 'array',
            'published' => 'boolean',
            'first_point_at' => 'datetime',
            'last_point_at' => 'datetime',
            'visible_until' => 'datetime',
            'point_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
