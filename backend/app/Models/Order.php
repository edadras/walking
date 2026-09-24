<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_points' => 'integer',
            'total_rial' => 'integer',
            'shipping_address' => 'array',
            'placed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }
}
