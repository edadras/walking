<?php

namespace App\Events;

use App\Models\WalkingSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Raised after commit for every newly accepted session; the Fraud Engine scores it (Phase 3). */
class WalkingSessionSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly WalkingSession $session) {}
}
