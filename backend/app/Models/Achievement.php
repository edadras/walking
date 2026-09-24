<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['threshold' => 'integer', 'xp_reward' => 'integer', 'point_reward' => 'integer', 'is_active' => 'boolean', 'sort' => 'integer'];
    }
}
