<?php

namespace App\Events;

use App\Models\WalkingSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Raised after the Fraud Engine has set verified steps and status. */
class WalkingSessionScored
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly WalkingSession $session) {}
}
