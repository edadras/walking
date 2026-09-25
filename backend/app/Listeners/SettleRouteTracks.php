<?php

namespace App\Listeners;

use App\Domain\Map\RouteMap;
use App\Enums\SessionKind;
use App\Events\WalkingSessionScored;
use Illuminate\Contracts\Queue\ShouldQueue;

/** A shared route goes on the public map (or is dropped) with its walk's verdict. */
class SettleRouteTracks implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly RouteMap $map) {}

    public function handle(WalkingSessionScored $event): void
    {
        $session = $event->session->fresh();
        if ($session !== null && $session->kind === SessionKind::Active) {
            $this->map->settleForSession($session);
        }
    }
}
