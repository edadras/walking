<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;

/**
 * Walking has a characteristic acceleration signature: a dominant frequency of
 * roughly 1.2–3.4 Hz and non-trivial variance. Shaker devices and hand-waving
 * tend to fall outside it. Suspicious minutes count at half value.
 */
class MotionSignatureRule extends AbstractRule
{
    public const KEY = 'motion_signature';

    public function name(): string
    {
        return 'امضای حرکتی غیرعادی';
    }

    public function category(): string
    {
        return 'motion';
    }

    public function defaults(): array
    {
        return ['min_hz' => 1.2, 'max_hz' => 3.4, 'min_std' => 0.5, 'min_steps' => 30, 'suspicious_share' => 0.5, 'risk' => 40];
    }

    public function evaluate(SessionContext $context, array $params): RuleResult
    {
        if (! $context->isActive()) {
            return RuleResult::pass();
        }

        $considered = 0;
        $caps = [];
        foreach ($context->buckets as $i => $b) {
            if ($b->steps < $params['min_steps'] || $b->accel_peak_hz === null || $b->accel_std === null) {
                continue;
            }
            $considered++;
            if ($b->accel_peak_hz < $params['min_hz'] || $b->accel_peak_hz > $params['max_hz'] || $b->accel_std < $params['min_std']) {
                $caps[$i] = intdiv($b->steps, 2);
            }
        }

        if ($considered === 0) {
            return RuleResult::pass();
        }
        $share = count($caps) / $considered;

        return new RuleResult(
            risk: $share >= $params['suspicious_share'] ? (int) $params['risk'] : 0,
            bucketCaps: $caps,
            evidence: $caps === [] ? [] : ['suspicious_minutes' => count($caps), 'share' => round($share, 2)],
        );
    }
}
