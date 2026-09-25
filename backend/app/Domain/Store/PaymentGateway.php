<?php

namespace App\Domain\Store;

use App\Domain\Store\Exceptions\GatewayUnavailable;

/**
 * Rial payment gateway (Spec: behind the `money_payment` flag). Amounts are in
 * Rial. Implementations talk to the gateway only; order state lives in
 * PaymentService.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Opens a payment and returns the gateway's reference and the page the user pays on.
     *
     * @return array{authority: string, url: string}
     *
     * @throws GatewayUnavailable
     */
    public function request(int $amountRial, string $callbackUrl, string $description, ?string $mobile = null): array;

    /**
     * Server-side confirmation for `$amountRial` (from our records). Null means not paid.
     *
     * @return array{ref_id: string, card_pan: ?string}|null
     *
     * @throws GatewayUnavailable
     */
    public function verify(int $amountRial, string $authority): ?array;
}
