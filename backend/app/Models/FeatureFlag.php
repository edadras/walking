<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $fillable = ['key', 'is_enabled', 'rollout_percent', 'min_app_version', 'platforms', 'description'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'rollout_percent' => 'integer',
        ];
    }
}
