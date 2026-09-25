<?php

namespace App\Domain\Notification;

use App\Models\Device;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pushe (pushe.co) REST API, for Bazaar/Myket builds where FCM is unreliable.
 * The device's push token is its Pushe device id; routing data travels in
 * `custom_content` so the app can open the right screen on tap.
 * Docs: docs.pushe.co/docs/mobile-api/filtered-notification, …/authentication.
 */
class PushePushSender implements PushSender
{
    public const URL = 'https://api.pushe.co/v2/messaging/notifications/';

    public function __construct(private readonly string $apiToken, private readonly string $appId) {}

    public function send(Device $device, string $title, string $body, array $data = []): void
    {
        if (! $device->push_token || $device->push_provider !== 'pushe') {
            return;
        }

        $response = Http::withHeaders(['Authorization' => 'Token '.$this->apiToken])->acceptJson()->timeout(8)->post(self::URL, [
            'app_ids' => $this->appId,
            'data' => ['title' => $title, 'content' => $body],
            'custom_content' => (object) array_map('strval', $data),
            'filters' => ['device_id' => [$device->push_token]],
        ]);

        if ($response->failed()) {
            Log::warning('pushe.send_failed', ['device' => $device->public_id, 'status' => $response->status()]);
        }
    }
}
