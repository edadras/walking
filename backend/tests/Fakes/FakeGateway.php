<?php

namespace Tests\Fakes;

use App\Domain\Store\Exceptions\GatewayUnavailable;
use App\Domain\Store\PaymentGateway;

/** In-memory gateway: `verified[authority] = amount` marks a payment as paid for that amount. */
class FakeGateway implements PaymentGateway
{
    public array $verified = [];

    public bool $down = false;

    public array $requested = [];

    public function name(): string
    {
        return 'zarinpal';
    }

    public function request(int $amountRial, string $callbackUrl, string $description, ?string $mobile = null): array
    {
        if ($this->down) {
            throw new GatewayUnavailable('down');
        }
        $authority = 'A'.str_pad((string) (count($this->requested) + 1), 35, '0', STR_PAD_LEFT);
        $this->requested[] = compact('amountRial', 'callbackUrl', 'authority');

        return ['authority' => $authority, 'url' => 'https://pay.test/'.$authority];
    }

    public function verify(int $amountRial, string $authority): ?array
    {
        if ($this->down) {
            throw new GatewayUnavailable('down');
        }

        return ($this->verified[$authority] ?? null) === $amountRial ? ['ref_id' => 'REF'.substr($authority, -3), 'card_pan' => '6037**1234'] : null;
    }
}
