<?php

namespace App\Http\Presenters;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductCode;
use Illuminate\Support\Facades\Storage;

class StorePresenter
{
    public function category(Category $c): array
    {
        return ['id' => $c->slug, 'name' => $c->name, 'icon' => $c->icon, 'parent' => $c->parent?->slug];
    }

    public function product(Product $p, bool $full = false): array
    {
        $images = $p->images->map(fn ($i) => Storage::disk('public')->url($i->path))->values();

        return [
            'id' => $p->public_id,
            'slug' => $p->slug,
            'name' => $p->name,
            'summary' => $p->summary,
            'type' => $p->type->value,
            'type_label' => $p->type->label(),
            'point_price' => $p->point_price,
            'in_stock' => $p->inStock(),
            'stock' => $p->stock !== null && $p->stock <= 10 ? $p->stock : null,
            'max_per_user' => $p->max_per_user,
            'min_level' => $p->min_level,
            'needs_address' => $p->type->needsAddress(),
            'image_url' => $images->first(),
            'category' => $p->category?->slug,
            'sponsor' => $p->sponsor?->name,
            ...($full ? ['description' => $p->description, 'images' => $images] : []),
        ];
    }

    public function address(Address $a): array
    {
        return ['id' => $a->public_id, ...$a->snapshot(), 'is_default' => $a->is_default];
    }

    public function order(Order $o, bool $full = false): array
    {
        return [
            'id' => $o->public_id,
            'number' => '#'.strtoupper(substr($o->public_id, -6)),
            'status' => $o->status->value,
            'status_label' => $o->status->label(),
            'total_points' => $o->total_points,
            'placed_at' => $o->placed_at->toIso8601String(),
            'item_count' => $o->items->sum('quantity'),
            'title' => $o->items->first()?->name,
            'image_url' => $this->imageUrl($o->items->first()),
            'cancellable' => $o->status === OrderStatus::Paid,
            ...($full ? [
                'items' => $o->items->map(fn (OrderItem $i) => $this->item($i))->values(),
                'shipping_address' => $o->shipping_address,
                'tracking_code' => $o->tracking_code,
                'history' => $o->history->map(fn (OrderStatusHistory $h) => ['status' => $h->to_status->value, 'label' => $h->to_status->label(), 'note' => $h->note, 'at' => $h->created_at->toIso8601String()])->values(),
            ] : []),
        ];
    }

    private function item(OrderItem $i): array
    {
        return [
            'name' => $i->name,
            'type' => $i->type->value,
            'quantity' => $i->quantity,
            'unit_point_price' => $i->unit_point_price,
            'image_url' => $this->imageUrl($i),
            // Codes are only ever shown to their owner, on the order detail.
            'codes' => $i->codes->map(fn (ProductCode $c) => ['code' => $c->code, 'expires_at' => $c->expires_at?->toIso8601String()])->values(),
            'coupon_id' => $i->userCoupon?->public_id,
        ];
    }

    private function imageUrl(?OrderItem $i): ?string
    {
        $path = $i?->product?->images->first()?->path;

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
