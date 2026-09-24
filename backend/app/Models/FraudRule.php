<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FraudRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'weight' => 'integer', 'params' => 'json'];
    }
}
