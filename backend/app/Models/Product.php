<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasPublicId, SoftDeletes;

    protected $guarded = ['id', 'public_id', 'sold_count'];

    protected $hidden = ['id'];

    protected $attributes = ['is_active' => false, 'sold_count' => 0, 'min_level' => 1, 'sort' => 0];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'point_price' => 'integer',
            'rial_price' => 'integer',
            'stock' => 'integer',
            'max_per_user' => 'integer',
            'min_level' => 'integer',
            'is_active' => 'boolean',
            'sold_count' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(ProductCode::class);
    }

    public function inStock(int $qty = 1): bool
    {
        return $this->stock === null || $this->stock >= $qty;
    }

    /** Digital-code stock is the number of unassigned codes. */
    public function syncCodeStock(): void
    {
        if ($this->type === ProductType::DigitalCode) {
            $this->forceFill(['stock' => $this->codes()->whereNull('order_item_id')->count()])->save();
        }
    }
}
