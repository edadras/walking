<?php

namespace App\Domain\Device\Integrity;

use App\Enums\IntegrityVerdict;

final readonly class IntegrityResult
{
    public function __construct(
        public IntegrityVerdict $verdict,
        public bool $appRecognized = false,
        public ?string $reason = null,
    ) {}

    public static function unavailable(string $reason): self
    {
        return new self(IntegrityVerdict::Unavailable, false, $reason);
    }
}
