<?php

namespace App\Domain\Notification;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

class LogPushSender implements PushSender
{
    public function send(Device $device, string $title, string $body, array $data = []): void
    {
        Log::info('push', ['device' => $device->public_id, 'title' => $title, 'data' => $data]);
    }
}
