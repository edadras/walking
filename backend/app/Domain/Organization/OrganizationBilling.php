<?php

namespace App\Domain\Organization;

use App\Domain\Audit\AuditLogger;
use App\Domain\Store\Exceptions\GatewayUnavailable;
use App\Domain\Store\PaymentGateway;
use App\Exceptions\ApiException;
use App\Models\Organization;
use App\Models\OrganizationInvoice;
use App\Models\OrganizationUser;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Seat subscription paid online; a verified payment extends `paid_until` and sets the seat count. */
class OrganizationBilling
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function available(): bool
    {
        return app()->bound(PaymentGateway::class);
    }

    public function quote(Organization $org, int $seats, int $months): int
    {
        return $seats * $months * $org->seat_price_rial;
    }

    public function start(Organization $org, OrganizationUser $by, int $seats, int $months): Payment
    {
        if (! $this->available()) {
            throw new ApiException('payment_gateway_unavailable', 'درگاه پرداخت پیکربندی نشده است.', 503);
        }
        if ($seats < max(1, $org->members()->count()) || $seats > 100000 || ! in_array($months, [1, 3, 6, 12], true)) {
            throw ApiException::unprocessable('invoice_invalid', 'تعداد صندلی باید دست‌کم برابر اعضای فعلی باشد و دوره ۱، ۳، ۶ یا ۱۲ ماه.');
        }
        $invoice = OrganizationInvoice::query()->create(['organization_id' => $org->id, 'organization_user_id' => $by->id, 'seats' => $seats, 'months' => $months,
            'amount_rial' => $this->quote($org, $seats, $months), 'status' => 'pending']);
        $gateway = app(PaymentGateway::class);
        try {
            $opened = $gateway->request($invoice->amount_rial, route('payments.callback', ['gateway' => $gateway->name()]), 'اشتراک سازمانی '.$invoice->number().' — گام‌یار');
        } catch (GatewayUnavailable $e) {
            $invoice->forceFill(['status' => 'failed'])->save();
            report($e);

            throw new ApiException('payment_gateway_unavailable', 'درگاه پرداخت در دسترس نیست. کمی بعد دوباره تلاش کنید.', 503);
        }

        return Payment::query()->create(['organization_invoice_id' => $invoice->id, 'gateway' => $gateway->name(), 'authority' => $opened['authority'],
            'amount_rial' => $invoice->amount_rial, 'pay_url' => $opened['url'], 'status' => Payment::PENDING]);
    }

    /** @param array{ref_id: string, card_pan: ?string}|null $verified */
    public function settle(Payment $payment, ?array $verified, string $failure): Payment
    {
        return DB::transaction(function () use ($payment, $verified, $failure) {
            $invoice = OrganizationInvoice::query()->whereKey($payment->organization_invoice_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== Payment::PENDING) {
                return $payment;
            }
            if ($verified === null) {
                $payment->forceFill(['status' => Payment::FAILED, 'failure' => $failure])->save();
                $invoice->forceFill(['status' => 'failed'])->save();

                return $payment;
            }
            $payment->forceFill(['status' => Payment::PAID, 'ref_id' => $verified['ref_id'], 'card_pan' => $verified['card_pan'], 'verified_at' => now()])->save();
            if ($invoice->status === 'pending') {
                $org = Organization::query()->whereKey($invoice->organization_id)->lockForUpdate()->firstOrFail();
                $today = CarbonImmutable::today('Asia/Tehran');
                // Renewal continues the current period; a lapsed subscription restarts today.
                $from = $org->paid_until !== null && $org->paid_until->gte($today) ? CarbonImmutable::parse($org->paid_until)->addDay() : $today;
                $to = $from->addMonthsNoOverflow($invoice->months)->subDay();
                $org->forceFill(['paid_until' => $to->toDateString(), 'seats' => $invoice->seats])->save();
                $invoice->forceFill(['status' => 'paid', 'ref_id' => $verified['ref_id'], 'paid_at' => now(), 'period_from' => $from->toDateString(), 'period_to' => $to->toDateString()])->save();
                $this->audit->log('organization.invoice_paid', $org, meta: ['invoice' => $invoice->public_id, 'amount_rial' => $invoice->amount_rial, 'ref_id' => $verified['ref_id']]);
            }

            return $payment;
        });
    }
}
