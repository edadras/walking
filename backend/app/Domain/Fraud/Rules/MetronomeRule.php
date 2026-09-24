<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;

/** Real walks fluctuate minute to minute; a perfectly constant cadence suggests a machine. */
class MetronomeRule extends AbstractRule
{
    public const KEY = 'metronome_pattern';

    public function name(): string
    {
        return 'الگوی مکانیکی (بیش از حد یکنواخت)';
    }

    public function category(): string
    {
        return 'motion';
    }

    public function defaults(): array
    {
        return ['min_minutes' => 10, 'max_cv' => 0.02, 'risk' => 50];
    }

    public function evaluate(SessionContext $context, array $params): RuleResult
    {
        if (! $context->isActive()) {
            return RuleResult::pass();
        }
        $values = $context->buckets->filter(fn ($b) => $b->duration_s === 60 && $b->steps > 0)->pluck('steps')->all();
        if (count($values) < $params['min_minutes']) {
            return RuleResult::pass();
        }

        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / count($values);
        $cv = $mean > 0 ? sqrt($variance) / $mean : 0;

        return $cv < $params['max_cv']
            ? new RuleResult(risk: (int) $params['risk'], evidence: ['cv' => round($cv, 4), 'minutes' => count($values)])
            : RuleResult::pass();
    }
}
