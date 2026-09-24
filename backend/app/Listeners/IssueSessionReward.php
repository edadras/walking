<?php

namespace App\Listeners;

use App\Domain\Reward\RewardEngine;
use App\Events\WalkingSessionScored;
use Illuminate\Contracts\Queue\ShouldQueue;

class IssueSessionReward implements ShouldQueue
{
    public string $queue = 'critical';

    public int $tries = 5;

    public function __construct(private readonly RewardEngine $rewards) {}

    public function handle(WalkingSessionScored $event): void
    {
        $this->rewards->forSession($event->session->fresh());
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30, 60, 300];
    }
}
