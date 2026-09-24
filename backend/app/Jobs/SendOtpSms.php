<?php

namespace App\Jobs;

use App\Domain\Auth\Sms\SmsSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SendOtpSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** An OTP is useless after it expires; don't retry for longer than that. */
    public int $maxExceptions = 2;

    public function __construct(public readonly string $phone, public readonly string $code)
    {
        $this->onQueue('critical');
    }

    public function backoff(): array
    {
        return [5, 15];
    }

    public function handle(SmsSender $sms): void
    {
        $sms->sendOtp($this->phone, $this->code);
    }
}
