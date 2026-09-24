<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;

/** A device clock far from server time at submission hints at time manipulation. */
class ClockSkewRule extends AbstractRule
{
    public const KEY = 'clock_skew';

    public function name(): string
    {
        return 'تغییر ساعت دستگاه';
    }

    public function category(): string
    {
        return 'time';
    }

    public function defaults(): array
    {
        return ['max_skew_seconds' => 120, 'risk' => 20];
    }

    public function evaluate(SessionContext $context, array $params): RuleResult
    {
        $skew = (int) $context->motion('clock_skew_s', 0);

        return $skew > $params['max_skew_seconds'] ? new RuleResult(risk: (int) $params['risk'], evidence: ['skew_s' => $skew]) : RuleResult::pass();
    }
}
