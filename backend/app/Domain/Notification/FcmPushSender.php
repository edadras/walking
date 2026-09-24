<?php

namespace App\Domain\Notification;

use App\Models\Device;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Firebase Cloud Messaging HTTP v1. Credentials: a service-account JSON with
 * the Firebase Messaging scope. Invalid tokens are cleared from the device.
 */
class FcmPushSender implements PushSender
{
    /** @param array<string, string> $credentials */
    public function __construct(private readonly array $credentials) {}

    public function send(Device $device, string $title, string $body, array $data = []): void
    {
        if (! $device->push_token || $device->push_provider !== 'fcm') {
            return;
        }

        $response = Http::withToken($this->accessToken())->timeout(8)->post(
            "https://fcm.googleapis.com/v1/projects/{$this->credentials['project_id']}/messages:send",
            ['message' => [
                'token' => $device->push_token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => array_map('strval', $data),
                'android' => ['priority' => 'normal', 'notification' => ['channel_id' => 'general']],
            ]],
        );

        if (in_array($response->json('error.status'), ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
            $device->forceFill(['push_token' => null])->saveQuietly();
        }
    }

    private function accessToken(): string
    {
        return Cache::remember('fcm:access_token', 3000, function () {
            $now = time();
            $b64 = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
            $segments = [
                $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $b64(json_encode([
                    'iss' => $this->credentials['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];
            openssl_sign(implode('.', $segments), $signature, $this->credentials['private_key'], OPENSSL_ALGO_SHA256);
            $segments[] = $b64($signature);

            return Http::asForm()->timeout(8)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ])->throw()->json('access_token');
        });
    }
}
