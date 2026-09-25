<?php

namespace App\Domain\Store;

use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\FeatureFlags;
use App\Domain\Sponsor\CouponService;
use App\Domain\Wallet\WalletService;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\TransactionType;
use App\Enums\UserCouponStatus;
use App\Exceptions\ApiException;
use App\Models\Address;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCode;
use App\Models\User;
use App\Models\UserCoupon;
use App\Notifications\UserNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Point purchases (docs/phase-0/05-flows.md §5.7). One DB transaction:
 * lock products in id order → validate → debit wallet (idempotent on the
 * order) → conditional stock decrement → assign codes / issue coupons.
 * Any failure rolls everything back. Replays with the same Idempotency-Key
 * return the first order.
 */
class OrderService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly CouponService $coupons,
        private readonly FeatureFlags $flags,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{product_id: string, quantity: int}>  $lines
     * @return array{0: Order, 1: bool} [order, created]
     */
    public function place(User $user, array $lines, string $idempotencyKey, ?string $addressId = null, string $paymentMode = 'points', ?string $note = null): array
    {
        $existing = Order::query()->where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return [$existing, false];
        }
        if (! $this->flags->enabled('store', $user->id)) {
            throw ApiException::forbidden('feature_disabled', 'فروشگاه در حال حاضر فعال نیست.');
        }
        if ($paymentMode !== 'points' && (! $this->flags->enabled('money_payment', $user->id) || ! app()->bound(PaymentGateway::class))) {
            throw ApiException::unprocessable('payment_unavailable', 'پرداخت ریالی در حال حاضر فعال نیست.');
        }
        $lines = collect($lines)->groupBy('product_id')->map(fn (Collection $g, string $id) => ['product_id' => $id, 'quantity' => (int) $g->sum('quantity')])->values();

        $money = $paymentMode === 'money';
        try {
            $order = DB::transaction(fn () => $this->placeLocked($user, $lines, $idempotencyKey, $addressId, $note, $money), attempts: 3);
        } catch (UniqueConstraintViolationException) {
            // A parallel retry of the same request won the race.
            return [Order::query()->where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->firstOrFail(), false];
        }

        if ($money) {
            // Stock is held; the goods are handed over only after the gateway confirms payment.
            app(PaymentService::class)->start($order);

            return [$order, true];
        }

        $user->notify(new UserNotification('order_update', 'سفارش ثبت شد', 'سفارش '.$this->shortId($order).' با '.number_format($order->total_points).' امتیاز ثبت شد.', ['type' => 'order', 'id' => $order->public_id]));

        return [$order, true];
    }

    private function placeLocked(User $user, Collection $lines, string $key, ?string $addressId, ?string $note, bool $money = false): Order
    {
        $products = Product::query()->whereIn('public_id', $lines->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('public_id');
        if ($products->count() !== $lines->count()) {
            throw ApiException::unprocessable('product_unavailable', 'یکی از کالاها دیگر موجود نیست.');
        }

        $total = 0;
        $totalRial = 0;
        foreach ($lines as $line) {
            $p = $products[$line['product_id']];
            $qty = $line['quantity'];
            $this->assertPurchasable($user, $p, $qty);
            if ($money && ! $p->rial_price) {
                throw ApiException::unprocessable('payment_unavailable', "«{$p->name}» فقط با امتیاز قابل خرید است.");
            }
            $total += $p->point_price * $qty;
            $totalRial += (int) $p->rial_price * $qty;
        }

        $address = null;
        if ($products->contains(fn (Product $p) => $p->type->needsAddress())) {
            $address = $addressId ? Address::query()->where('user_id', $user->id)->where('public_id', $addressId)->first() : null;
            if ($address === null) {
                throw ApiException::unprocessable('address_required', 'برای ارسال کالا یک نشانی انتخاب کن.');
            }
        }

        if ($money) {
            return $this->placeAwaitingPayment($user, $lines, $products, $key, $address, $note, $totalRial);
        }

        $order = Order::query()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'payment_mode' => 'points',
            'total_points' => $total,
            'idempotency_key' => $key,
            'shipping_address' => $address?->snapshot(),
            'user_note' => $note,
            'placed_at' => now(),
        ]);

        // Throws InsufficientPoints (409) → the whole order rolls back.
        $tx = $this->wallet->debit($user, $total, TransactionType::Purchase, 'order:'.$order->public_id, 'خرید '.$this->shortId($order), $order);

        $instant = true;
        foreach ($lines as $line) {
            $p = $products[$line['product_id']];
            $qty = $line['quantity'];
            $this->takeStock($p, $qty);
            $item = OrderItem::query()->create([
                'order_id' => $order->id, 'product_id' => $p->id, 'name' => $p->name, 'type' => $p->type,
                'quantity' => $qty, 'unit_point_price' => $p->point_price,
            ]);
            match ($p->type) {
                ProductType::DigitalCode => $this->assignCodes($p, $item, $qty),
                ProductType::Coupon => $this->issueCoupon($user, $p, $item, $order),
                default => null,
            };
            $instant = $instant && $p->type->deliveredInstantly();
        }

        $order->forceFill(['point_transaction_id' => $tx->id, 'status' => $instant ? OrderStatus::Delivered : OrderStatus::Paid])->save();
        $this->history($order, null, OrderStatus::Paid, null, 'user', $user->id);
        if ($instant) {
            $this->history($order, OrderStatus::Paid, OrderStatus::Delivered, 'تحویل خودکار', 'system', null);
        }

        return $order;
    }

    /** Rial order: reserve stock and wait for the gateway; nothing is delivered yet. */
    private function placeAwaitingPayment(User $user, Collection $lines, Collection $products, string $key, ?Address $address, ?string $note, int $totalRial): Order
    {
        $order = Order::query()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::AwaitingPayment,
            'payment_mode' => 'money',
            'total_points' => 0,
            'total_rial' => $totalRial,
            'idempotency_key' => $key,
            'shipping_address' => $address?->snapshot(),
            'user_note' => $note,
            'placed_at' => now(),
        ]);
        foreach ($lines as $line) {
            $p = $products[$line['product_id']];
            $this->takeStock($p, $line['quantity']);
            OrderItem::query()->create([
                'order_id' => $order->id, 'product_id' => $p->id, 'name' => $p->name, 'type' => $p->type,
                'quantity' => $line['quantity'], 'unit_point_price' => 0, 'unit_rial_price' => (int) $p->rial_price,
            ]);
        }
        $this->history($order, null, OrderStatus::AwaitingPayment, null, 'user', $user->id);

        return $order;
    }

    /**
     * Gateway confirmed payment: hand over codes/coupons exactly like a points purchase.
     * Must run inside the caller's transaction with the order row locked.
     */
    public function fulfilPaid(Order $order, string $reference): void
    {
        $order->loadMissing('items.product.coupon');
        $instant = true;
        foreach ($order->items as $item) {
            $p = $item->product;
            match ($item->type) {
                ProductType::DigitalCode => $this->assignCodes($p, $item, $item->quantity),
                ProductType::Coupon => $this->issueCoupon($order->user, $p, $item, $order),
                default => null,
            };
            $instant = $instant && $item->type->deliveredInstantly();
        }
        $order->forceFill(['status' => $instant ? OrderStatus::Delivered : OrderStatus::Paid])->save();
        $this->history($order, OrderStatus::AwaitingPayment, OrderStatus::Paid, 'پرداخت ریالی، کد پیگیری '.$reference, 'system', null);
        if ($instant) {
            $this->history($order, OrderStatus::Paid, OrderStatus::Delivered, 'تحویل خودکار', 'system', null);
        }
    }

    /** Unpaid rial order: give the held stock back. Caller holds the order lock. */
    public function releaseUnpaid(Order $order, string $reason): void
    {
        $order->loadMissing('items');
        foreach ($order->items as $item) {
            DB::table('products')->where('id', $item->product_id)->whereNotNull('stock')->increment('stock', $item->quantity);
            DB::table('products')->where('id', $item->product_id)->where('sold_count', '>=', $item->quantity)->decrement('sold_count', $item->quantity);
        }
        $order->forceFill(['status' => OrderStatus::Cancelled])->save();
        $this->history($order, OrderStatus::AwaitingPayment, OrderStatus::Cancelled, $reason, 'system', null);
    }

    private function assertPurchasable(User $user, Product $p, int $qty): void
    {
        if (! $p->is_active || $p->point_price <= 0) {
            throw ApiException::unprocessable('product_unavailable', "«{$p->name}» دیگر قابل خرید نیست.");
        }
        if (($user->level ?? 1) < $p->min_level) {
            throw ApiException::unprocessable('level_required', "برای خرید «{$p->name}» باید به سطح {$p->min_level} برسی.");
        }
        if (! $p->inStock($qty)) {
            throw ApiException::conflict('out_of_stock', "موجودی «{$p->name}» کافی نیست.");
        }
        if ($p->type === ProductType::Coupon && $qty !== 1) {
            throw ApiException::unprocessable('quantity_invalid', 'از هر کوپن فقط یکی قابل خرید است.');
        }
        if ($p->max_per_user !== null) {
            $bought = (int) OrderItem::query()->where('product_id', $p->id)
                ->whereHas('order', fn ($q) => $q->where('user_id', $user->id)->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Refunded]))
                ->sum('quantity');
            if ($bought + $qty > $p->max_per_user) {
                throw ApiException::conflict('limit_reached', "سقف خرید «{$p->name}» برای هر نفر {$p->max_per_user} عدد است.");
            }
        }
    }

    private function takeStock(Product $p, int $qty): void
    {
        $q = DB::table('products')->where('id', $p->id);
        if ($p->stock !== null) {
            $q->where('stock', '>=', $qty);
        }
        $changes = ['sold_count' => DB::raw('sold_count + '.$qty)];
        if ($p->stock !== null) {
            $changes['stock'] = DB::raw('stock - '.$qty);
        }
        if ($q->update($changes) === 0) {
            throw ApiException::conflict('out_of_stock', "موجودی «{$p->name}» کافی نیست.");
        }
    }

    private function assignCodes(Product $p, OrderItem $item, int $qty): void
    {
        $ids = ProductCode::query()->where('product_id', $p->id)->whereNull('order_item_id')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('id')->limit($qty)->lockForUpdate()->pluck('id');
        if ($ids->count() < $qty) {
            throw ApiException::conflict('out_of_stock', "کد کافی برای «{$p->name}» موجود نیست.");
        }
        ProductCode::query()->whereIn('id', $ids)->update(['order_item_id' => $item->id, 'assigned_at' => now()]);
    }

    private function issueCoupon(User $user, Product $p, OrderItem $item, Order $order): void
    {
        $uc = $p->coupon ? $this->coupons->issue($user, $p->coupon, 'order', $order->id, 'order-item:'.$item->id) : null;
        if ($uc === null) {
            throw ApiException::conflict('out_of_stock', "کوپن «{$p->name}» تمام شده است.");
        }
        $item->forceFill(['user_coupon_id' => $uc->id])->save();
    }

    /** Admin fulfilment / refunds. */
    public function transition(Order $order, OrderStatus $to, Admin $admin, ?string $note = null, ?string $tracking = null): Order
    {
        return DB::transaction(function () use ($order, $to, $admin, $note, $tracking) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $locked->status->canMoveTo($to)) {
                throw ApiException::conflict('invalid_transition', 'این تغییر وضعیت مجاز نیست.');
            }
            $from = $locked->status;
            if ($to === OrderStatus::Refunded) {
                $this->refund($locked, $note ?? 'بازگشت توسط پشتیبانی');
            }
            $locked->forceFill(['status' => $to, 'tracking_code' => $tracking ?? $locked->tracking_code])->save();
            $this->history($locked, $from, $to, $note, 'admin', $admin->id);
            $this->audit->log('order.'.$to->value, $locked, ['status' => $from->value], ['status' => $to->value, 'tracking_code' => $tracking], ['note' => $note], $admin);

            $locked->user->notify(new UserNotification('order_update', 'سفارش '.$this->shortId($locked).': '.$to->label(),
                $tracking ? 'کد رهگیری: '.$tracking : ($note ?? ''), ['type' => 'order', 'id' => $locked->public_id]));

            return $locked;
        });
    }

    /** The buyer can cancel while nothing has been prepared yet. */
    public function cancelByUser(User $user, Order $order): Order
    {
        return DB::transaction(function () use ($user, $order) {
            $locked = Order::query()->whereKey($order->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === OrderStatus::AwaitingPayment) {
                Payment::query()->where('order_id', $locked->id)->where('status', Payment::PENDING)->update(['status' => Payment::CANCELLED]);
                $this->releaseUnpaid($locked, 'لغو توسط کاربر پیش از پرداخت');

                return $locked;
            }
            if ($locked->status !== OrderStatus::Paid || $locked->payment_mode === 'money') {
                throw ApiException::conflict('not_cancellable', 'این سفارش دیگر قابل لغو نیست.');
            }
            $this->refund($locked, 'لغو توسط کاربر');
            $locked->forceFill(['status' => OrderStatus::Refunded])->save();
            $this->history($locked, OrderStatus::Paid, OrderStatus::Refunded, 'لغو توسط کاربر', 'user', $user->id);

            return $locked;
        });
    }

    private function refund(Order $order, string $reason): void
    {
        $order->loadMissing('items.userCoupon');
        foreach ($order->items as $item) {
            if ($item->userCoupon !== null) {
                $revoked = UserCoupon::query()->whereKey($item->user_coupon_id)->where('status', UserCouponStatus::Available)->update(['status' => UserCouponStatus::Revoked]);
                if ($revoked === 0) {
                    throw ApiException::conflict('coupon_used', 'کوپن این سفارش استفاده شده و قابل بازگشت نیست.');
                }
            }
            // Physical/service stock returns; delivered codes are not reusable.
            if (in_array($item->type, [ProductType::Physical, ProductType::Service], true)) {
                DB::table('products')->where('id', $item->product_id)->whereNotNull('stock')->increment('stock', $item->quantity);
            }
            DB::table('products')->where('id', $item->product_id)->where('sold_count', '>=', $item->quantity)->decrement('sold_count', $item->quantity);
        }
        if ($order->total_points <= 0) {
            // Rial orders are refunded to the card outside the app; only goods/coupons are reversed here.
            return;
        }
        $tx = $this->wallet->credit($order->user, $order->total_points, TransactionType::Refund, 'refund:order:'.$order->public_id, 'بازگشت امتیاز سفارش '.$this->shortId($order), $order, ['reason' => $reason]);
        $order->forceFill(['refund_transaction_id' => $tx->id]);
    }

    private function history(Order $order, ?OrderStatus $from, OrderStatus $to, ?string $note, string $actorType, ?int $actorId): void
    {
        OrderStatusHistory::query()->create(['order_id' => $order->id, 'from_status' => $from, 'to_status' => $to, 'note' => $note, 'actor_type' => $actorType, 'actor_id' => $actorId]);
    }

    public function shortId(Order $order): string
    {
        return '#'.strtoupper(substr($order->public_id, -6));
    }
}
