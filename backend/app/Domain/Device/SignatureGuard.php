<?php

namespace App\Domain\Device;

use App\Domain\Settings\Settings;
use App\Exceptions\ApiException;
use Illuminate\Http\Request;

/** Validates timestamp, nonce and signature headers of a request against a public key. */
class SignatureGuard
{
    public const HEADER_TIMESTAMP = 'X-Timestamp';

    public const HEADER_NONCE = 'X-Nonce';

    public const HEADER_SIGNATURE = 'X-Signature';

    public function __construct(private readonly NonceStore $nonces, private readonly Settings $settings) {}

    public function assertValid(Request $request, string $publicKeyPem, string $nonceScope): void
    {
        $timestamp = (string) $request->header(self::HEADER_TIMESTAMP, '');
        $nonce = (string) $request->header(self::HEADER_NONCE, '');
        $signature = (string) $request->header(self::HEADER_SIGNATURE, '');

        if (! ctype_digit($timestamp) || ! preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce) || $signature === '') {
            throw new ApiException('signature_missing', 'درخواست امضای معتبر ندارد.', 400);
        }

        $skew = abs(time() - (int) $timestamp);
        if ($skew > $this->settings->int('security.signature_max_skew_seconds')) {
            throw new ApiException('timestamp_skew', 'ساعت دستگاه شما با زمان واقعی هماهنگ نیست. لطفاً تنظیم خودکار ساعت را روشن کنید.', 400, ['server_time' => time()]);
        }

        $canonical = RequestSignature::canonical($request->method(), '/'.ltrim($request->path(), '/'), $timestamp, $nonce, $request->getContent());

        if (! RequestSignature::verify($publicKeyPem, $canonical, $signature)) {
            throw new ApiException('signature_invalid', 'امضای درخواست نامعتبر است.', 400);
        }

        // Claim the nonce only after the signature is valid so garbage can't fill the store.
        if (! $this->nonces->claim($nonceScope, $nonce, $this->settings->int('security.nonce_ttl_seconds'))) {
            throw new ApiException('nonce_reused', 'این درخواست قبلاً ارسال شده است.', 400);
        }

        $request->attributes->set('clock_skew_seconds', $skew);
    }
}
