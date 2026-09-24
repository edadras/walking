<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Replaced or removed uploads would otherwise pile up on the public disk.
        static::updated(function (self $image) {
            if ($image->wasChanged('path') && ($old = $image->getOriginal('path'))) {
                Storage::disk('public')->delete($old);
            }
        });
        static::deleted(fn (self $image) => Storage::disk('public')->delete($image->path));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
