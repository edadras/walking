<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;

/** Implied speeds above ~50 m/s between fixes are counted on-device as jumps. */
class GpsTeleportRule extends AbstractRule
{
    public const KEY = 'gps_teleport';

    public function name(): string
    {
        return 'جهش مکانی (Teleport)';
    }

    public function category(): string
    {
        return 'gps';
    }

    public function defaults(): array
    {
        return ['max_jumps' => 2, 'risk' => 60];
    }

    public function evaluate(SessionContext $context, array $params): RuleResult
    {
        $jumps = (int) $context->gps('jumps', 0);

        return $jumps > $params['max_jumps'] ? new RuleResult(risk: (int) $params['risk'], evidence: ['jumps' => $jumps]) : RuleResult::pass();
    }
}
