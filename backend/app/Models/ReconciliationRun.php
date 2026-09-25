<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationRun extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['issues' => 'array', 'totals' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
