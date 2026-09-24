<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A gift-card / license code. Stored encrypted; the hash prevents importing duplicates. */
class ProductCode extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['code'];

    protected function casts(): array
    {
        return ['code' => 'encrypted', 'assigned_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function hashOf(string $code): string
    {
        return hash('sha256', trim($code));
    }
}
