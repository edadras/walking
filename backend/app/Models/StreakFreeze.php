<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreakFreeze extends Model
{
    protected $guarded = ['id'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PointTransaction::class, 'point_transaction_id');
    }

    protected function casts(): array
    {
        return ['covered_date' => 'date', 'used_at' => 'datetime'];
    }
}
