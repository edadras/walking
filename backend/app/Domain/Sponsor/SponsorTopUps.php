<?php

namespace App\Domain\Sponsor;

use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\Settings;
use App\Domain\Store\Exceptions\GatewayUnavailable;
use App\Domain\Store\PaymentGateway;
use App\Domain\Wallet\ConversionRate;
use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\Sponsor;
use App\Models\SponsorTopUp;
use App\Models\SponsorUser;
use Illuminate\Support\Facades\DB;

/**
 * Self-serve budget: the sponsor pays rial through the gateway and receives
 * points in its budget pool at the sponsor price. Credited only after the
 * gateway's server-side verification, exactly once (row lock + status check).
 */
class SponsorTopUps
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ConversionRate $rates,
        private readonly AuditLogger $audit,
    ) {}

    public function available(): bool
    {
        return app()->bound(PaymentGateway::class);
    }

    public function pricePerPoint(): int
    {
        return $this->settings->int('sponsors.point_price_rial') ?: $this->rates->current();
    }

    /** @return array{min: int, max: int, price: int} */
    public function limits(): array
    {
        return ['min' => $this->settings->int('sponsors.min_topup_rial'), 'max' => $this->settings->int('sponsors.max_topup_rial'), 'price' => $this->pricePerPoint()];
    }

    public function start(Sponsor $sponsor, SponsorUser $by, int $amountRial): Payment
    {
        if (! $this->available()) {
            throw new ApiException('payment_gateway_unavailable', 'درگاه پرداخت پیکربندی نشده است.', 503);
        }
        if (! $sponsor->isApproved()) {
            throw ApiException::forbidden('sponsor_not_approved', 'حساب اسپانسر هنوز تأیید نشده است.');
        }
        $limits = $this->limits();
        if ($amountRial < $limits['min'] || $amountRial > $limits['max']) {
            throw ApiException::unprocessable('amount_out_of_range', 'مبلغ شارژ باید بین '.number_format($limits['min']).' و '.number_format($limits['max']).' ریال باشد.');
        }
        $points = intdiv($amountRial, $limits['price']);
        $topUp = SponsorTopUp::query()->create(['sponsor_id' => $sponsor->id, 'sponsor_user_id' => $by->id, 'amount_rial' => $amountRial,
            'points' => $points, 'price_rial_per_point' => $limits['price'], 'status' => SponsorTopUp::PENDING]);

        $gateway = app(PaymentGateway::class);
        try {
            $opened = $gateway->request($amountRial, route('payments.callback', ['gateway' => $gateway->name()]), 'شارژ اعتبار '.$topUp->number().' — گام‌یار');
        } catch (GatewayUnavailable $e) {
            $topUp->forceFill(['status' => SponsorTopUp::FAILED])->save();
            report($e);

            throw new ApiException('payment_gateway_unavailable', 'درگاه پرداخت در دسترس نیست. کمی بعد دوباره تلاش کنید.', 503);
        }
        $this->audit->log('sponsor.topup_started', $topUp, new: ['amount_rial' => $amountRial, 'points' => $points]);

        return Payment::query()->create(['sponsor_top_up_id' => $topUp->id, 'gateway' => $gateway->name(), 'authority' => $opened['authority'],
            'amount_rial' => $amountRial, 'pay_url' => $opened['url'], 'status' => Payment::PENDING]);
    }

    /** Called by PaymentService for payments that belong to a top-up. @param array{ref_id: string, card_pan: ?string}|null $verified */
    public function settle(Payment $payment, ?array $verified, string $failure): Payment
    {
        return DB::transaction(function () use ($payment, $verified, $failure) {
            $topUp = SponsorTopUp::query()->whereKey($payment->sponsor_top_up_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== Payment::PENDING) {
                return $payment;
            }
            if ($verified === null) {
                $payment->forceFill(['status' => Payment::FAILED, 'failure' => $failure])->save();
                $topUp->forceFill(['status' => SponsorTopUp::FAILED])->save();

                return $payment;
            }
            $payment->forceFill(['status' => Payment::PAID, 'ref_id' => $verified['ref_id'], 'card_pan' => $verified['card_pan'], 'verified_at' => now()])->save();
            if ($topUp->status === SponsorTopUp::PENDING) {
                DB::table('sponsors')->where('id', $topUp->sponsor_id)->increment('point_budget', $topUp->points);
                $topUp->forceFill(['status' => SponsorTopUp::PAID, 'ref_id' => $verified['ref_id'], 'paid_at' => now()])->save();
                $this->audit->log('sponsor.budget_topped_up', $topUp->sponsor, meta: ['points' => $topUp->points, 'amount_rial' => $topUp->amount_rial,
                    'ref_id' => $verified['ref_id'], 'top_up' => $topUp->public_id, 'channel' => 'online']);
            }

            return $payment;
        });
    }
}
