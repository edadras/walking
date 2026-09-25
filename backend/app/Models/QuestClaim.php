<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestClaim extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['claimed_at' => 'datetime', 'progress' => 'integer'];
    }
}
