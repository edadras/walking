<?php

namespace App\Domain\Sponsor;

use App\Domain\Settings\Settings;
use App\Models\Location;
use Carbon\CarbonInterface;

/**
 * Rotating QR shown on the branch screen (Sponsor panel → «QR شعبه»).
 *
 *   token = "GY1." location_public_id "." window "." nonce "." base64url(HMAC-SHA256(qr_secret, location.window.nonce))[0..22]
 *
 * window = floor(unix / qr_window_s). The server accepts the current and the
 * previous window only, so a photographed code is useless after ≤ 60 s — and a
 * QR never rewards on its own: the visit must also be inside the geofence for
 * the minimum stay.
 */
class QrToken
{
    private const PREFIX = 'GY1';

    public function __construct(private readonly Settings $settings) {}

    public function windowSeconds(): int
    {
        return max(10, $this->settings->int('visits.qr_window_s'));
    }

    public function window(?CarbonInterface $at = null): int
    {
        return intdiv(($at ?? now())->getTimestamp(), $this->windowSeconds());
    }

    public function issue(Location $location, ?CarbonInterface $at = null): string
    {
        $window = $this->window($at);
        // Stable for the whole window, so the code on screen doesn't change while someone scans it.
        $nonce = substr(hash_hmac('sha256', 'nonce.'.$window, $location->qr_secret), 0, 8);

        return implode('.', [self::PREFIX, $location->public_id, $window, $nonce, $this->mac($location, $window, $nonce)]);
    }

    /** Seconds until the token shown now stops being the current one. */
    public function secondsLeft(?CarbonInterface $at = null): int
    {
        $w = $this->windowSeconds();

        return $w - (($at ?? now())->getTimestamp() % $w);
    }

    /** Returns the verification failure reason, or null when the token is valid for this location now. */
    public function verify(string $token, Location $location, ?CarbonInterface $at = null): ?string
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 5 || $parts[0] !== self::PREFIX) {
            return 'qr_malformed';
        }
        [, $locationId, $window, $nonce, $mac] = $parts;
        if (! ctype_digit($window) || ! preg_match('/^[a-z0-9]{8}$/', $nonce)) {
            return 'qr_malformed';
        }
        if (! hash_equals($location->public_id, $locationId)) {
            return 'qr_wrong_location';
        }
        if (! hash_equals($this->mac($location, (int) $window, $nonce), $mac)) {
            return 'qr_invalid';
        }
        $now = $this->window($at);
        if ((int) $window > $now || (int) $window < $now - 1) {
            return 'qr_expired';
        }

        return null;
    }

    private function mac(Location $location, int $window, string $nonce): string
    {
        $raw = hash_hmac('sha256', "{$location->public_id}.{$window}.{$nonce}", $location->qr_secret, true);

        return substr(rtrim(strtr(base64_encode($raw), '+/', '-_'), '='), 0, 22);
    }
}
