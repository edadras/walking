<?php

namespace App\Domain\Auth\Sms;

use Illuminate\Support\Facades\Log;

/** Development driver. Never bound in production (see AppServiceProvider). */
class LogSmsSender implements SmsSender
{
    public function sendOtp(string $phone, string $code): void
    {
        Log::info('sms.otp', ['phone' => $phone, 'code' => $code]);
    }
}
