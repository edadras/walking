<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reward extends Model
{
    use HasUlids;

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['id', 'user_id'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected function casts(): array
    {
        return [
            'breakdown' => 'json',
            'multiplier' => 'float',
            'base_points' => 'integer',
            'bonus_points' => 'integer',
            'capped_points' => 'integer',
            'final_points' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PointTransaction::class, 'point_transaction_id');
    }
}
