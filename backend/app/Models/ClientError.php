<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientError extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['occurrences' => 1, 'fatal' => false];

    protected function casts(): array
    {
        return [
            'fatal' => 'boolean',
            'occurrences' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function lastUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user_id');
    }
}
