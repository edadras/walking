<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDeletionRequest extends Model
{
    protected $fillable = ['user_id', 'status', 'requested_at', 'scheduled_for', 'completed_at'];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
