<?php

namespace App\Support;

/**
 * Keyed, non-reversible hashes of personal identifiers (national code, Sheba, IP) used
 * for uniqueness and clustering. The key is PII_HASH_KEY, not APP_KEY: APP_KEY may be
 * rotated, this one must stay stable (rotating it resets IP clustering history).
 */
final class PiiHash
{
    public static function key(): string
    {
        // Falls back to APP_KEY for local development; `ops:preflight` refuses that in production.
        return (string) (config('walk.security.pii_hash_key') ?: config('app.key'));
    }

    public static function make(string $purpose, string $value): string
    {
        return hash_hmac('sha256', $purpose.'|'.$value, self::key());
    }
}
