<?php

namespace App\Domain\Auth\Sms;

use Illuminate\Support\Facades\Http;

/** Kavenegar "verify/lookup" template API (template-based OTP delivery). */
class KavenegarSmsSender implements SmsSender
{
    public function __construct(private readonly string $apiKey, private readonly string $template, private readonly ?string $cashoutTemplate = null) {}

    public function sendOtp(string $phone, string $code, string $purpose = 'login'): void
    {
        Http::asForm()
            ->timeout(10)
            ->retry(2, 500)
            ->post("https://api.kavenegar.com/v1/{$this->apiKey}/verify/lookup.json", [
                'receptor' => '0'.substr($phone, 3),
                'token' => $code,
                // Payout confirmations use their own template so the SMS says what is being approved.
                'template' => $purpose === 'cashout' ? ($this->cashoutTemplate ?: $this->template) : $this->template,
            ])
            ->throw();
    }
}
