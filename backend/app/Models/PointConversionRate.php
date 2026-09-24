<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only history; the current rate is the latest effective one. */
class PointConversionRate extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rial_per_point' => 'integer', 'effective_from' => 'datetime'];
    }
}
