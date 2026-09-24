<?php

namespace App\Domain\Notification;

use App\Models\Device;

interface PushSender
{
    /** @param array<string, string> $data */
    public function send(Device $device, string $title, string $body, array $data = []): void;
}
