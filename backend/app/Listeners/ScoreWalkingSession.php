<?php

namespace App\Listeners;

use App\Domain\Fraud\FraudEngine;
use App\Events\WalkingSessionSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;

class ScoreWalkingSession implements ShouldQueue
{
    public string $queue = 'fraud';

    public int $tries = 5;

    public function __construct(private readonly FraudEngine $engine) {}

    public function handle(WalkingSessionSubmitted $event): void
    {
        $this->engine->score($event->session->fresh());
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60, 300];
    }
}
