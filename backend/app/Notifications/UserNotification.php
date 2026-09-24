<?php

namespace App\Notifications;

use App\Domain\Notification\PushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** One notification type for the app: stored in the inbox and pushed if allowed. */
class UserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, scalar> $data deep-link payload */
    public function __construct(
        public readonly string $category,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
        public readonly bool $bypassQuietHours = false,
    ) {
        $this->onQueue('notifications');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', PushChannel::class];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['category' => $this->category, 'title' => $this->title, 'body' => $this->body, 'data' => $this->data];
    }
}
