<?php

namespace App\Domain\Fraud\Contracts;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;

/**
 * One signal of the multi-signal Fraud Engine. Rules never decide alone: they
 * return a risk contribution and/or step caps that the engine combines.
 * A future ML model plugs in as just another rule.
 */
interface FraudRule
{
    public function key(): string;

    public function name(): string;

    public function category(): string;

    /** @return array<string, int|float|bool> admin-tunable parameters with safe defaults */
    public function defaults(): array;

    /** @param array<string, mixed> $params */
    public function evaluate(SessionContext $context, array $params): RuleResult;
}
