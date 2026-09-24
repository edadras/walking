<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserStreak extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['current_days' => 'integer', 'longest_days' => 'integer', 'last_goal_date' => 'immutable_date'];
    }
}
