<?php

namespace App\Models;

use App\Enums\QuestMetric;
use Illuminate\Database\Eloquent\Model;

class Quest extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['is_active' => true, 'sort' => 0, 'reward_points' => 0, 'reward_xp' => 0];

    protected function casts(): array
    {
        return ['metric' => QuestMetric::class, 'target' => 'integer', 'reward_points' => 'integer', 'reward_xp' => 'integer', 'is_active' => 'boolean'];
    }
}
