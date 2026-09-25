<?php

namespace App\Domain\Store;

use App\Domain\Audit\AuditLogger;
use App\Domain\Store\Exceptions\GatewayUnavailable;
use App\Enums\OrderStatus;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\Payment;
use App\Notifications\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rial order lifecycle around the gateway:
 *   start()    → a pending Payment + the gateway page URL
 *   complete() → callback: verify our amount server-side, then fulfil or release
 *   sweep()    → abandoned payments: verify once (the callback may have been lost), else release
 * Every step is idempotent and runs under the order row lock.
 */
class PaymentService
{
    /** A payment page older than this is treated as abandoned. */
    public const EXPIRES_MINUTES = 20;

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OrderService $orders,
        private readonly AuditLogger $audit,
    ) {}

    public function start(Order $order): Payment
    {
        try {
            $opened = $this->gateway->request(
                $order->total_rial,
                route('payments.callback', ['gateway' => $this->gateway->name()]),
                'سفارش '.$this->orders->shortId($order).' — گام‌یار',
                $order->user->phone,
            );
        } catch (GatewayUnavailable $e) {
            DB::transaction(function () use ($order) {
                $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($locked->status === OrderStatus::AwaitingPayment) {
                    $this->orders->releaseUnpaid($locked, 'درگاه پرداخت در دسترس نبود');
                }
            });
            report($e);

            throw new ApiException('payment_gateway_unavailable', 'درگاه پرداخت در دسترس نیست. کمی بعد دوباره تلاش کن.', 503);
        }

        return Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => $this->gateway->name(),
            'authority' => $opened['authority'],
            'amount_rial' => $order->total_rial,
            'pay_url' => $opened['url'],
            'status' => Payment::PENDING,
        ]);
    }

    /** Page URL for a still-pending payment, so the app can resume it. */
    public function resumeUrl(Payment $payment): ?string
    {
        return $payment->status === Payment::PENDING && $payment->created_at->gte(now()->subMinutes(self::EXPIRES_MINUTES)) ? $payment->pay_url : null;
    }

    /** Gateway callback. `$ok` is only a hint from the browser redirect; verification decides. */
    public function complete(string $authority, bool $ok): ?Payment
    {
        $payment = Payment::query()->where('authority', $authority)->where('gateway', $this->gateway->name())->first();
        if ($payment === null) {
            return null;
        }
        if ($payment->status !== Payment::PENDING) {
            return $payment; // Refreshing the result page must not re-run anything.
        }

        if (! $ok) {
            return $this->settle($payment, null, 'انصراف یا خطا در درگاه');
        }

        try {
            $verified = $this->gateway->verify($payment->amount_rial, $payment->authority);
        } catch (GatewayUnavailable $e) {
            // Unknown outcome: leave it pending; sweep() re-verifies before giving the stock back.
            report($e);

            return $payment;
        }

        return $this->settle($payment, $verified, 'پرداخت تأیید نشد');
    }

    /** Pending payments past expiry: one last verification, then release. */
    public function sweep(): int
    {
        $count = 0;
        Payment::query()->where('status', Payment::PENDING)->where('created_at', '<', now()->subMinutes(self::EXPIRES_MINUTES))
            ->orderBy('id')->limit(200)->get()
            ->each(function (Payment $payment) use (&$count) {
                try {
                    $verified = $this->gateway->verify($payment->amount_rial, $payment->authority);
                } catch (GatewayUnavailable) {
                    return; // Try again on the next run; never release while the answer is unknown.
                }
                $this->settle($payment, $verified, 'مهلت پرداخت تمام شد');
                $count++;
            });

        return $count;
    }

    /** @param  array{ref_id: string, card_pan: ?string}|null  $verified */
    private function settle(Payment $payment, ?array $verified, string $failure): Payment
    {
        return DB::transaction(function () use ($payment, $verified, $failure) {
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== Payment::PENDING) {
                return $payment;
            }

            if ($verified === null) {
                $payment->forceFill(['status' => Payment::FAILED, 'failure' => $failure])->save();
                if ($order->status === OrderStatus::AwaitingPayment) {
                    $this->orders->releaseUnpaid($order, $failure);
                }

                return $payment;
            }

            $payment->forceFill(['status' => Payment::PAID, 'ref_id' => $verified['ref_id'], 'card_pan' => $verified['card_pan'], 'verified_at' => now()])->save();
            if ($order->status !== OrderStatus::AwaitingPayment) {
                // Paid after we had already given up (e.g. the user cancelled): money must go back by hand.
                $this->audit->log('payment.paid_after_release', $order, meta: ['payment' => $payment->public_id, 'ref_id' => $verified['ref_id']]);
                Log::warning('payment.paid_after_release', ['order' => $order->public_id, 'ref_id' => $verified['ref_id']]);

                return $payment;
            }

            try {
                DB::transaction(fn () => $this->orders->fulfilPaid($order, $verified['ref_id']));
            } catch (Throwable $e) {
                // Money is taken; never lose that. The order stays paid for support to hand over manually.
                report($e);
                $order->forceFill(['status' => OrderStatus::Paid])->save();
                $this->audit->log('payment.fulfilment_failed', $order, meta: ['payment' => $payment->public_id, 'error' => $e->getMessage()]);
            }
            $this->audit->log('payment.paid', $order, meta: ['payment' => $payment->public_id, 'ref_id' => $verified['ref_id'], 'amount_rial' => $payment->amount_rial]);
            $order->user->notify(new UserNotification('order_update', 'پرداخت موفق',
                'سفارش '.$this->orders->shortId($order).' پرداخت شد. کد پیگیری: '.$verified['ref_id'], ['type' => 'order', 'id' => $order->public_id]));

            return $payment;
        });
    }
}
