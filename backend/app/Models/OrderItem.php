<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['type' => ProductType::class, 'quantity' => 'integer', 'unit_point_price' => 'integer', 'unit_rial_price' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function codes(): HasMany
    {
        return $this->hasMany(ProductCode::class);
    }

    public function userCoupon(): BelongsTo
    {
        return $this->belongsTo(UserCoupon::class);
    }
}
