<?php

namespace App\Support;

final class Phone
{
    /**
     * Normalise an Iranian mobile number to E.164 (+989XXXXXXXXX).
     * Accepts Persian/Arabic digits and the usual 09 / 9 / 989 / +989 / 00989 forms.
     */
    public static function normalize(string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', Digits::toLatin($input)) ?? '';

        $digits = match (true) {
            str_starts_with($digits, '0098') => substr($digits, 4),
            str_starts_with($digits, '98') && strlen($digits) === 12 => substr($digits, 2),
            str_starts_with($digits, '0') && strlen($digits) === 11 => substr($digits, 1),
            default => $digits,
        };

        return preg_match('/^9\d{9}$/', $digits) ? '+98'.$digits : null;
    }

    /** +989121234567 → 0912***4567 */
    public static function mask(string $e164): string
    {
        $local = '0'.substr($e164, 3);

        return substr($local, 0, 4).'***'.substr($local, -4);
    }
}
