<?php

namespace App\Domain\Store;

use App\Models\Order;

/**
 * Rial payment adapter (Spec: behind the `money_payment` flag). No gateway is
 * bound yet: a concrete adapter (e.g. an IPG) must be implemented against the
 * gateway's official docs and bound in AppServiceProvider before enabling it.
 */
interface PaymentGateway
{
    /** Starts a payment and returns the URL the app opens. */
    public function start(Order $order): string;

    /** Verifies the gateway callback server-side; returns the gateway reference on success. */
    public function verify(Order $order, array $callback): ?string;
}
