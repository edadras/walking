<?php

namespace App\Domain\Device\Integrity;

use App\Enums\IntegrityVerdict;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Decodes Play Integrity tokens with Google's decodeIntegrityToken endpoint
 * (server-side; the client never sees or reports the verdict itself).
 * Credentials: a service-account JSON with the Play Integrity API enabled.
 */
class PlayIntegrityVerifier implements IntegrityVerifier
{
    private const SCOPE = 'https://www.googleapis.com/auth/playintegrity';

    private const MAX_TOKEN_AGE_MS = 5 * 60 * 1000;

    /** @param array<string, string> $credentials */
    public function __construct(private readonly array $credentials, private readonly string $packageName) {}

    public function verify(?string $token, string $expectedRequestHash): IntegrityResult
    {
        if (! $token) {
            return IntegrityResult::unavailable('no_token');
        }

        try {
            $response = Http::withToken($this->accessToken())
                ->timeout(8)
                ->post("https://playintegrity.googleapis.com/v1/{$this->packageName}:decodeIntegrityToken", [
                    'integrity_token' => $token,
                ]);
        } catch (Throwable $e) {
            Log::warning('play_integrity.request_failed', ['error' => $e->getMessage()]);

            return IntegrityResult::unavailable('request_failed');
        }

        if (! $response->successful()) {
            Log::warning('play_integrity.decode_failed', ['status' => $response->status()]);

            return IntegrityResult::unavailable('decode_failed');
        }

        $payload = $response->json('tokenPayloadExternal', []);

        $request = $payload['requestDetails'] ?? [];
        if (($request['requestPackageName'] ?? null) !== $this->packageName) {
            return new IntegrityResult(IntegrityVerdict::None, false, 'package_mismatch');
        }
        if (! hash_equals($expectedRequestHash, (string) ($request['requestHash'] ?? ''))) {
            return new IntegrityResult(IntegrityVerdict::None, false, 'request_hash_mismatch');
        }
        if (abs((int) (microtime(true) * 1000) - (int) ($request['timestampMillis'] ?? 0)) > self::MAX_TOKEN_AGE_MS) {
            return new IntegrityResult(IntegrityVerdict::None, false, 'stale_token');
        }

        $appRecognized = ($payload['appIntegrity']['appRecognitionVerdict'] ?? null) === 'PLAY_RECOGNIZED';
        $labels = $payload['deviceIntegrity']['deviceRecognitionVerdict'] ?? [];

        $verdict = match (true) {
            in_array('MEETS_STRONG_INTEGRITY', $labels, true) => IntegrityVerdict::Strong,
            in_array('MEETS_DEVICE_INTEGRITY', $labels, true) => IntegrityVerdict::Device,
            in_array('MEETS_BASIC_INTEGRITY', $labels, true) => IntegrityVerdict::Basic,
            default => IntegrityVerdict::None,
        };

        return new IntegrityResult($verdict, $appRecognized, $appRecognized ? null : 'app_not_recognized');
    }

    private function accessToken(): string
    {
        return Cache::remember('play_integrity:access_token', 3000, function () {
            $now = time();
            $segments = [
                $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->b64(json_encode([
                    'iss' => $this->credentials['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];
            openssl_sign(implode('.', $segments), $signature, $this->credentials['private_key'], OPENSSL_ALGO_SHA256);
            $segments[] = $this->b64($signature);

            return Http::asForm()->timeout(8)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ])->throw()->json('access_token');
        });
    }

    private function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
