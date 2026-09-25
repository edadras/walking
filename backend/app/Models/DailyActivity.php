<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyActivity extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'local_date' => 'immutable_date',
            'goal_reached_at' => 'datetime',
            'raw_steps' => 'integer',
            'verified_steps' => 'integer',
            'distance_m' => 'integer',
            'cycling_distance_m' => 'integer',
            'calories_kcal' => 'float',
            'active_minutes' => 'integer',
            'goal_steps' => 'integer',
            'points_earned' => 'integer',
            'cycling_points' => 'integer',
            'rewarded_steps' => 'integer',
            'sessions_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
