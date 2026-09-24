<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'birth_year',
        'gender',
        'height_cm',
        'weight_kg',
        'daily_step_goal',
        'water_goal_ml',
        'water_reminder_enabled',
        'water_reminder_interval_min',
        'quiet_hours_start',
        'quiet_hours_end',
    ];

    protected function casts(): array
    {
        return [
            'birth_year' => 'integer',
            'height_cm' => 'integer',
            'weight_kg' => 'float',
            'daily_step_goal' => 'integer',
            'water_goal_ml' => 'integer',
            'water_reminder_enabled' => 'boolean',
            'water_reminder_interval_min' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
