<?php

namespace App\Domain\Notification;

use App\Enums\NotificationCategory;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\UserNotification;
use Carbon\CarbonImmutable;

/**
 * Push delivery honouring the user's per-category switches and quiet hours.
 * Only devices with a live session receive pushes.
 */
class PushChannel
{
    public function __construct(private readonly PushSender $sender) {}

    public function send(User $user, UserNotification $notification): void
    {
        $category = NotificationCategory::tryFrom($notification->category);
        if ($category !== null && ! $category->isMandatory()) {
            $enabled = $user->notificationPreferences()->where('category', $category->value)->value('push_enabled');
            if ($enabled === false || $enabled === 0) {
                return;
            }
        }
        if (! $notification->bypassQuietHours && $this->inQuietHours($user)) {
            return;
        }

        $deviceIds = PersonalAccessToken::query()->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id)->pluck('device_id');
        $user->devices()->whereIn('devices.id', $deviceIds)->whereNotNull('push_token')->get()
            ->each(fn ($device) => $this->sender->send($device, $notification->title, $notification->body, ['category' => $notification->category, ...array_map('strval', $notification->data)]));
    }

    private function inQuietHours(User $user): bool
    {
        $profile = $user->profile;
        if (! $profile?->quiet_hours_start || ! $profile->quiet_hours_end) {
            return false;
        }
        $now = CarbonImmutable::now($user->timezone)->format('H:i:s');
        $start = $profile->quiet_hours_start;
        $end = $profile->quiet_hours_end;

        return $start <= $end ? ($now >= $start && $now < $end) : ($now >= $start || $now < $end);
    }
}
