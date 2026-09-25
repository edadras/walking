<?php

namespace App\Domain\Auth\Sms;

interface SmsSender
{
    public function sendOtp(string $phone, string $code, string $purpose = 'login'): void;
}
