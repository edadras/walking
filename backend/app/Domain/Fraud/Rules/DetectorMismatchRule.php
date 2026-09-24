<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;

/**
 * Step Counter and Step Detector are separate sensor pipelines. On a real walk
 * they agree closely; injected counter values or replayed data usually don't.
 */
class DetectorMismatchRule extends AbstractRule
{
    public const KEY = 'detector_mismatch';

    public function name(): string
    {
        return 'ناهمخوانی شمارنده و آشکارساز قدم';
    }

    public function category(): string
    {
        return 'motion';
    }

    public function defaults(): array
    {
        return ['max_difference' => 0.35, 'min_steps' => 300, 'risk' => 30];
    }

    public function evaluate(SessionContext $context, array $params): RuleResult
    {
        $ratio = $context->motion('detector_ratio');
        if (! $context->isActive() || $ratio === null || $context->session->raw_steps < $params['min_steps']) {
            return RuleResult::pass();
        }

        return abs(1 - (float) $ratio) > $params['max_difference']
            ? new RuleResult(risk: (int) $params['risk'], evidence: ['detector_ratio' => $ratio])
            : RuleResult::pass();
    }
}
