<?php

namespace App\Domain\Device\Integrity;

/** Used where Play Integrity isn't configured (local, CI). Never grants trust. */
class NullIntegrityVerifier implements IntegrityVerifier
{
    public function verify(?string $token, string $expectedRequestHash): IntegrityResult
    {
        return IntegrityResult::unavailable($token ? 'verifier_not_configured' : 'no_token');
    }
}
