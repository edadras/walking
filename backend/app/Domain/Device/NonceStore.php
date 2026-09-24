<?php

namespace App\Domain\Device;

use Illuminate\Support\Facades\Cache;

class NonceStore
{
    /** Atomically records the nonce; returns false if it was already used (replay). */
    public function claim(string $scope, string $nonce, int $ttlSeconds): bool
    {
        return Cache::add('nonce:'.$scope.':'.$nonce, 1, $ttlSeconds);
    }
}
