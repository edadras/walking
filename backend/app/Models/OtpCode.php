<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['phone', 'code_hash', 'purpose', 'attempts', 'expires_at', 'consumed_at', 'device_id', 'ip_hash'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
