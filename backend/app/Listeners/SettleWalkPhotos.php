<?php

namespace App\Listeners;

use App\Domain\Social\PostService;
use App\Events\WalkingSessionScored;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Photos taken on a walk go public (or are dropped) with the walk's verdict. */
class SettleWalkPhotos implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly PostService $posts) {}

    public function handle(WalkingSessionScored $event): void
    {
        $this->posts->settleForSession($event->session->fresh());
    }
}
