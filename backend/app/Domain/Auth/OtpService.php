<?php

namespace App\Domain\Auth;

use App\Domain\Settings\Settings;
use App\Exceptions\ApiException;
use App\Jobs\SendOtpSms;
use App\Models\Device;
use App\Models\OtpCode;
use App\Support\Ip;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class OtpService
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * Issues a new code. The response is identical for registered and new numbers
     * (no user enumeration). Rate limited per phone, device and IP.
     *
     * @return array{expires_in:int, resend_in:int}
     */
    public function request(string $phone, Device $device, ?string $ip): array
    {
        $resend = $this->settings->int('auth.otp_resend_seconds');

        $this->hit("otp:resend:$phone", 1, $resend);
        $this->hit("otp:phone:$phone", 5, 3600);
        $this->hit("otp:device:{$device->id}", 10, 3600);
        $this->hit('otp:ip:'.Ip::hash($ip), 20, 3600);

        $length = $this->settings->int('auth.otp_length');
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
        $ttl = $this->settings->int('auth.otp_ttl_seconds');

        DB::transaction(function () use ($phone, $code, $ttl, $device, $ip) {
            // Only the most recent code is ever valid.
            OtpCode::query()->where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            OtpCode::query()->create([
                'phone' => $phone,
                'code_hash' => self::hash($phone, $code),
                'expires_at' => now()->addSeconds($ttl),
                'device_id' => $device->id,
                'ip_hash' => Ip::hash($ip),
            ]);
        });

        SendOtpSms::dispatch($phone, $code);

        return ['expires_in' => $ttl, 'resend_in' => $resend];
    }

    /** Verifies and consumes the current code. Row-locked so parallel guesses can't exceed the attempt limit. */
    public function verify(string $phone, string $code): void
    {
        $this->hit("otp:verify:$phone", 10, 3600);

        $error = DB::transaction(function () use ($phone, $code) {
            $otp = OtpCode::query()
                ->where('phone', $phone)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $max = $this->settings->int('auth.otp_max_attempts');

            if ($otp === null || $otp->expires_at->isPast() || $otp->attempts >= $max) {
                return ApiException::unprocessable('otp_expired', 'کد منقضی شده است. لطفاً کد جدید دریافت کنید.');
            }

            $otp->increment('attempts');

            if (! hash_equals($otp->code_hash, self::hash($phone, $code))) {
                $remaining = $max - $otp->attempts;

                return $remaining > 0
                    ? ApiException::unprocessable('otp_invalid', 'کد وارد شده صحیح نیست.', ['remaining_attempts' => $remaining])
                    : ApiException::unprocessable('otp_expired', 'تعداد تلاش‌ها بیش از حد مجاز شد. لطفاً کد جدید دریافت کنید.');
            }

            $otp->forceFill(['consumed_at' => now()])->save();

            return null;
        });

        // Thrown outside the transaction so the attempt counter increment is committed.
        if ($error !== null) {
            throw $error;
        }
    }

    public static function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', $phone.'|'.$code, (string) config('app.key'));
    }

    private function hit(string $key, int $max, int $decay): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw new ApiException(
                'too_many_requests',
                'تعداد درخواست‌ها زیاد است. کمی بعد دوباره تلاش کنید.',
                429,
                ['retry_after' => RateLimiter::availableIn($key)],
            );
        }
        RateLimiter::hit($key, $decay);
    }
}
