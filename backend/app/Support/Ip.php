<?php

namespace App\Support;

final class Ip
{
    /** IPs are stored only as keyed hashes (still usable for clustering, not reversible). */
    public static function hash(?string $ip): ?string
    {
        return $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;
    }
}
