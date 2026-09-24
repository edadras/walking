<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdProvider extends Model
{
    public const INTERNAL = 'internal';

    protected $guarded = ['id'];

    protected $hidden = ['webhook_secret', 'config'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'webhook_secret' => 'encrypted', 'config' => 'encrypted:array'];
    }

    public function isInternal(): bool
    {
        return $this->key === self::INTERNAL;
    }
}
