<?php

namespace App\Domain\Store;

use App\Domain\Store\Exceptions\GatewayUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Zarinpal REST v4: payment/request.json → StartPay/{authority} → callback with
 * Authority & Status → payment/verify.json (code 100 = verified now, 101 = was
 * already verified). Sandbox uses the same paths on sandbox.zarinpal.com.
 */
class ZarinpalGateway implements PaymentGateway
{
    public function __construct(private readonly string $merchantId, private readonly bool $sandbox = false) {}

    public function name(): string
    {
        return 'zarinpal';
    }

    private function api(): string
    {
        return $this->sandbox ? 'https://sandbox.zarinpal.com' : 'https://api.zarinpal.com';
    }

    private function startPay(string $authority): string
    {
        return ($this->sandbox ? 'https://sandbox.zarinpal.com' : 'https://www.zarinpal.com').'/pg/StartPay/'.$authority;
    }

    public function request(int $amountRial, string $callbackUrl, string $description, ?string $mobile = null): array
    {
        $data = $this->post('/pg/v4/payment/request.json', [
            'merchant_id' => $this->merchantId,
            'amount' => $amountRial,
            'currency' => 'IRR',
            'callback_url' => $callbackUrl,
            'description' => $description,
            'metadata' => array_filter(['mobile' => $mobile]),
        ]);

        if (($data['code'] ?? null) !== 100 || empty($data['authority'])) {
            throw new GatewayUnavailable('zarinpal request rejected: '.($data['code'] ?? 'no code'));
        }

        return ['authority' => (string) $data['authority'], 'url' => $this->startPay((string) $data['authority'])];
    }

    public function verify(int $amountRial, string $authority): ?array
    {
        $data = $this->post('/pg/v4/payment/verify.json', ['merchant_id' => $this->merchantId, 'amount' => $amountRial, 'authority' => $authority]);

        if (in_array($data['code'] ?? null, [100, 101], true) && isset($data['ref_id'])) {
            return ['ref_id' => (string) $data['ref_id'], 'card_pan' => isset($data['card_pan']) ? (string) $data['card_pan'] : null];
        }

        return null;
    }

    /** @return array<string, mixed> the `data` object; an `errors` answer is a definitive "no" (empty data). */
    private function post(string $path, array $body): array
    {
        try {
            $response = Http::asJson()->acceptJson()->timeout(15)->post($this->api().$path, $body);
        } catch (ConnectionException $e) {
            throw new GatewayUnavailable('zarinpal unreachable', previous: $e);
        }
        if ($response->serverError()) {
            throw new GatewayUnavailable('zarinpal '.$response->status());
        }

        $data = $response->json('data');

        return is_array($data) ? $data : [];
    }
}
