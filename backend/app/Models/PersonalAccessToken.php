<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/** Sanctum token bound to the device it was issued to. */
class PersonalAccessToken extends SanctumToken
{
    protected $fillable = ['name', 'token', 'abilities', 'expires_at', 'device_id'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
