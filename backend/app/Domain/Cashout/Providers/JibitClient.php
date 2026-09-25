<?php

namespace App\Domain\Cashout\Providers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Jibit REST client (https://napi.jibit.ir). Each product ("ide" inquiry,
 * "cobank" transfers) issues its own JWT from `/{module}/v1/tokens/generate` with the
 * product's apiKey/secretKey; tokens are cached until shortly before their `exp`.
 * Contract taken from Jibit's published OpenAPI document.
 */
class JibitClient
{
    public function __construct(private readonly string $baseUrl, private readonly array $credentials) {}

    public function get(string $module, string $path, array $query = []): Response
    {
        return $this->send($module, fn (string $token) => $this->http($token)->get($this->baseUrl.$path, $query));
    }

    public function post(string $module, string $path, array $body): Response
    {
        return $this->send($module, fn (string $token) => $this->http($token)->post($this->baseUrl.$path, $body));
    }

    private function http(string $token)
    {
        return Http::withToken($token)->acceptJson()->asJson()->timeout(15)->connectTimeout(5);
    }

    private function send(string $module, callable $call): Response
    {
        $response = $call($this->token($module));
        if (in_array($response->status(), [401, 403], true)) {
            // Token revoked/expired early: one retry with a fresh one.
            Cache::forget($this->cacheKey($module));
            $response = $call($this->token($module));
        }

        return $response;
    }

    private function cacheKey(string $module): string
    {
        return 'jibit:token:'.$module;
    }

    private function token(string $module): string
    {
        if ($cached = Cache::get($this->cacheKey($module))) {
            return $cached;
        }
        [$apiKey, $secret] = $this->credentials[$module] ?? [null, null];
        if (! $apiKey || ! $secret) {
            throw new RuntimeException("Jibit {$module} credentials are not configured.");
        }
        $response = Http::acceptJson()->asJson()->timeout(15)->post($this->baseUrl."/{$module}/v1/tokens/generate", ['apiKey' => $apiKey, 'secretKey' => $secret]);
        $token = $response->json('accessToken');
        if (! $response->successful() || ! is_string($token)) {
            throw new RuntimeException("Jibit {$module} token request failed: HTTP ".$response->status());
        }
        Cache::put($this->cacheKey($module), $token, $this->ttl($token));

        return $token;
    }

    /** Seconds until 60 s before the JWT's `exp` (10 minutes if it can't be read). */
    private function ttl(string $jwt): int
    {
        $payload = json_decode(base64_decode(strtr(explode('.', $jwt)[1] ?? '', '-_', '+/')) ?: 'null', true);
        $exp = is_array($payload) && isset($payload['exp']) ? (int) $payload['exp'] - time() - 60 : 600;

        return max(30, $exp);
    }
}
