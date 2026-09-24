<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['local_date' => 'immutable_date', 'logged_at' => 'datetime', 'amount_ml' => 'integer'];
    }
}
