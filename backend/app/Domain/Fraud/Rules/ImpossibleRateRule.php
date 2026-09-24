<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;

/** A minute with more steps than any human can take is discarded entirely. */
class ImpossibleRateRule extends AbstractRule
{
    public const KEY = 'impossible_rate';

    public function name(): string
    {
        return 'قدم غیرممکن در زمان کوتاه';
    }

    public function category(): string
    {
        return 'motion';
    }

    public function defaults(): array
    {
        return ['max_per_minute' => 250, 'risk' => 40];
    }

    public function evaluate(SessionContext $context, array $params): RuleResult
    {
        $caps = [];
        foreach ($context->buckets as $i => $b) {
            if ($b->duration_s <= 120 && SessionContext::cadence($b) > $params['max_per_minute']) {
                $caps[$i] = 0;
            }
        }

        return new RuleResult(risk: $caps === [] ? 0 : (int) $params['risk'], bucketCaps: $caps, evidence: $caps === [] ? [] : ['buckets' => count($caps)]);
    }
}
