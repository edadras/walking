<?php

namespace App\Domain\Ads;

/**
 * Signed receipt for one served ad. Events (impression/click) must carry it,
 * so clients can't report events for ads they were never shown, nor inflate
 * counters: each (serve, type) is stored once.
 */
class ServeToken
{
    private const TTL_SECONDS = 86_400;

    /** @param array{s: string, a: int, c: int, p: int, u: int, d: string} $claims */
    public function issue(array $claims): string
    {
        $payload = $this->b64(json_encode([...$claims, 'x' => now()->getTimestamp() + self::TTL_SECONDS]));

        return $payload.'.'.$this->mac($payload);
    }

    /** @return array{s: string, a: int, c: int, p: int, u: int, d: string, x: int}|null */
    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || ! hash_equals($this->mac($parts[0]), $parts[1])) {
            return null;
        }
        $claims = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
        if (! is_array($claims) || ($claims['x'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return $claims;
    }

    private function mac(string $payload): string
    {
        return $this->b64(hash_hmac('sha256', 'ads.'.$payload, (string) config('app.key'), true));
    }

    private function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
