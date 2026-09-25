<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;

/**
 * Steps logged while Activity Recognition says "in vehicle / on bicycle", or
 * while GPS speed is far above walking pace with a low cadence, are discarded.
 * Minutes the CyclingClassifier recognised as riding a bicycle are discarded as
 * steps too (they are rewarded by distance instead) but carry no risk.
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
        $cycling = array_flip($context->cycling['buckets']);
        $risky = 0;
        foreach ($context->buckets as $i => $b) {
            if (isset($cycling[$i])) {
                $caps[$i] = 0;

                continue;
            }
            $vehicleLabel = in_array($b->activity_type, ['vehicle', 'bicycle'], true);
            $fastWithoutStriding = $b->speed_mps !== null && $b->speed_mps > $params['max_walk_speed'] && SessionContext::cadence($b) < $params['run_cadence'];
            if ($vehicleLabel || $fastWithoutStriding) {
                $caps[$i] = 0;
                $risky++;
            }
        }
        $maxSpeed = (float) $context->gps('max_speed_mps', 0);
        // A recognised bike ride may briefly exceed the walking-session ceiling (downhill).
        $fastSession = $maxSpeed > $params['max_session_speed'] && ($cycling === [] || $maxSpeed > $params['max_session_speed'] * 1.5);

        return new RuleResult(
            risk: ($risky > 0 && $risky >= max(1, (int) ceil($context->buckets->count() / 3))) || $fastSession ? (int) $params['risk'] : 0,
            bucketCaps: $caps,
            evidence: array_filter(['vehicle_buckets' => $risky ?: null, 'cycling_buckets' => count($cycling) ?: null, 'max_speed_mps' => $fastSession ? $maxSpeed : null]),
        );
    }
}
