<?php

namespace App\Domain\Device\Integrity;

interface IntegrityVerifier
{
    /**
     * Decodes a Play Integrity token server-side and returns a normalised verdict.
     * `$expectedRequestHash` binds the token to this specific request.
     */
    public function verify(?string $token, string $expectedRequestHash): IntegrityResult;
}
