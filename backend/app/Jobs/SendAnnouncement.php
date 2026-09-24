<?php

namespace App\Jobs;

use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Fan-out of an admin announcement to every active user, in chunks. */
class SendAnnouncement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 1800;

    public function __construct(public readonly int $announcementId)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $a = Announcement::query()->find($this->announcementId);
        if ($a === null || $a->sent_at !== null) {
            return;
        }
        $count = 0;
        User::query()->where('status', UserStatus::Active)->chunkById(500, function ($users) use ($a, &$count) {
            foreach ($users as $user) {
                $user->notify(new UserNotification('announcement', $a->title, $a->body, array_filter(['type' => 'announcement', 'link' => $a->deep_link])));
                $count++;
            }
        });
        $a->forceFill(['sent_at' => now(), 'recipients' => $count])->save();
    }
}
