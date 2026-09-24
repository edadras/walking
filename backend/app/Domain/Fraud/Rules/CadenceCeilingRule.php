<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;

/**
 * Human cadence tops out around 200–220 steps/min (sprinting). Minutes above
 * the ceiling are capped; a sustained run of them adds risk. Coarse passive
 * windows get a lower sustained ceiling (nobody averages 150+/min for an hour).
 */
class CadenceCeilingRule extends AbstractRule
{
    public const KEY = 'cadence_ceiling';

    public function name(): string
    {
        return 'سقف آهنگ قدم';
    }

    public function category(): string
    {
        return 'motion';
    }

    public function defaults(): array
    {
        return ['max_cadence' => 220, 'cap_cadence' => 180, 'sustained_minutes' => 3, 'passive_max_cadence' => 150, 'risk' => 30];
    }

    public function evaluate(SessionContext $context, array $params): RuleResult
    {
        $caps = [];
        $over = 0;
        foreach ($context->buckets as $i => $b) {
            $cadence = SessionContext::cadence($b);
            $passiveWindow = $b->duration_s > 120;
            $limit = $passiveWindow ? $params['passive_max_cadence'] : $params['max_cadence'];
            if ($cadence > $limit) {
                $capTo = $passiveWindow ? $params['passive_max_cadence'] : $params['cap_cadence'];
                $caps[$i] = (int) floor($capTo * $b->duration_s / 60);
                $over += max(1, intdiv($b->duration_s, 60));
            }
        }

        return new RuleResult(
            risk: $over >= $params['sustained_minutes'] ? (int) $params['risk'] : 0,
            bucketCaps: $caps,
            evidence: $caps === [] ? [] : ['minutes_over' => $over],
        );
    }
}
