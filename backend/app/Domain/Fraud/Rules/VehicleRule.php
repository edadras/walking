<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;

/**
 * Steps logged while Activity Recognition says "in vehicle / on bicycle", or
 * while GPS speed is far above walking pace with a low cadence, are discarded.
 */
class VehicleRule extends AbstractRule
{
    public const KEY = 'vehicle_speed';

    public function name(): string
    {
        return 'حرکت با خودرو';
    }

    public function category(): string
    {
        return 'gps';
    }

    public function defaults(): array
    {
        return ['max_walk_speed' => 3.5, 'run_cadence' => 140, 'max_session_speed' => 12, 'risk' => 35];
    }

    public function evaluate(SessionContext $context, array $params): RuleResult
    {
        $caps = [];
        foreach ($context->buckets as $i => $b) {
            $vehicleLabel = in_array($b->activity_type, ['vehicle', 'bicycle'], true);
            $fastWithoutStriding = $b->speed_mps !== null && $b->speed_mps > $params['max_walk_speed'] && SessionContext::cadence($b) < $params['run_cadence'];
            if ($vehicleLabel || $fastWithoutStriding) {
                $caps[$i] = 0;
            }
        }
        $maxSpeed = (float) $context->gps('max_speed_mps', 0);
        $fastSession = $maxSpeed > $params['max_session_speed'];

        return new RuleResult(
            risk: ($caps !== [] && count($caps) >= max(1, (int) ceil($context->buckets->count() / 3))) || $fastSession ? (int) $params['risk'] : 0,
            bucketCaps: $caps,
            evidence: array_filter(['vehicle_buckets' => count($caps) ?: null, 'max_speed_mps' => $fastSession ? $maxSpeed : null]),
        );
    }
}
