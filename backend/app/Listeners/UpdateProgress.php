<?php

namespace App\Listeners;

use App\Domain\Gamification\ProgressService;
use App\Events\WalkingSessionScored;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateProgress implements ShouldQueue
{
    public string $queue = 'default';

    public int $tries = 3;

    public function __construct(private readonly ProgressService $progress) {}

    public function handle(WalkingSessionScored $event): void
    {
        $this->progress->afterSession($event->session->fresh());
    }
}
