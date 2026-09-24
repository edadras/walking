<?php

namespace App\Domain\Fraud\Data;

final class RuleResult
{
    /**
     * @param  int  $risk  0..100 before weighting
     * @param  array<int, int>  $bucketCaps  bucket index → max countable steps
     * @param  int|null  $sessionCap  max countable steps for the whole session
     * @param  array<string, mixed>  $evidence  shown to analysts; never to the user
     */
    public function __construct(
        public readonly int $risk = 0,
        public readonly array $bucketCaps = [],
        public readonly ?int $sessionCap = null,
        public readonly bool $hardReject = false,
        public readonly array $evidence = [],
    ) {}

    public static function pass(): self
    {
        return new self;
    }

    public function triggered(): bool
    {
        return $this->risk > 0 || $this->bucketCaps !== [] || $this->sessionCap !== null || $this->hardReject;
    }
}
