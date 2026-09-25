<?php

namespace App\Http\Controllers;

use App\Domain\Store\PaymentGateway;
use App\Domain\Store\PaymentService;
use App\Enums\OrderStatus;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where the bank sends the browser back. The query string is untrusted: it only
 * names the payment; the result comes from server-side verification.
 */
class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, string $gateway): View
    {
        abort_unless(app()->bound(PaymentGateway::class), 404);
        $authority = (string) $request->query('Authority', '');
        abort_unless(preg_match('/^[A-Za-z0-9]{8,64}$/', $authority) === 1, 404);

        $payment = app(PaymentService::class)->complete($authority, $request->query('Status') === 'OK');
        abort_if($payment === null, 404);
        $order = $payment->order;

        return view('payments.result', [
            'paid' => $payment->status === Payment::PAID,
            'pending' => $payment->status === Payment::PENDING,
            'refId' => $payment->ref_id,
            'number' => '#'.strtoupper(substr($order->public_id, -6)),
            'amount' => $payment->amount_rial,
            'needsSupport' => $payment->status === Payment::PAID && $order->status === OrderStatus::Cancelled,
            'appLink' => 'gamyar://app/orders/'.$order->public_id,
        ]);
    }
}
