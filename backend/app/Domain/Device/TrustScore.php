<?php

namespace App\Domain\Device;

use App\Enums\IntegrityVerdict;
use App\Models\Device;

/** Device-level prior used by the Fraud Engine. Client-side flags carry low weight. */
final class TrustScore
{
    public static function for(Device $device): int
    {
        $score = 50 + match ($device->integrity_verdict) {
            IntegrityVerdict::Strong => 40,
            IntegrityVerdict::Device => 30,
            IntegrityVerdict::Basic => 10,
            IntegrityVerdict::None => -35,
            default => 0,
        };

        if ($device->key_attested) {
            $score += 10;
        }
        if ($device->emulator_suspected) {
            $score -= 20;
        }
        if ($device->root_suspected) {
            $score -= 15;
        }

        return max(0, min(100, $score));
    }
}
