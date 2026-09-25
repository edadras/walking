<?php

namespace App\Domain\Notification;

use App\Models\Device;

/** Picks the sender for each device's registered provider (FCM on Play builds, Pushe elsewhere). */
class RoutingPushSender implements PushSender
{
    /** @param array<string, PushSender> $senders provider => sender */
    public function __construct(private readonly array $senders, private readonly PushSender $fallback) {}

    public function send(Device $device, string $title, string $body, array $data = []): void
    {
        ($this->senders[$device->push_provider] ?? $this->fallback)->send($device, $title, $body, $data);
    }
}
