<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;

class RepeatOffenderRule extends AbstractRule
{
    public const KEY = 'repeat_offender';

    public function name(): string
    {
        return 'تخلف تکراری';
    }

    public function category(): string
    {
        return 'account';
    }

    public function defaults(): array
    {
        return ['max_rejections' => 2, 'risk' => 40];
    }

    public function evaluate(SessionContext $context, array $params): RuleResult
    {
        return $context->recentRejections > $params['max_rejections']
            ? new RuleResult(risk: (int) $params['risk'], evidence: ['rejections_7d' => $context->recentRejections])
            : RuleResult::pass();
    }
}
