<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;

/**
 * Two phones walking for the same account at the same time: the overlapping
 * share of the later session is not counted.
 */
class OverlapRule extends AbstractRule
{
    public const KEY = 'overlapping_session';

    public function name(): string
    {
        return 'جلسه‌های همزمان';
    }

    public function category(): string
    {
        return 'time';
    }

    public function defaults(): array
    {
        return ['risk_share' => 0.5, 'risk' => 20];
    }

    public function evaluate(SessionContext $context, array $params): RuleResult
    {
        $s = $context->session;
        if ($s->overlap_s <= 0 || $s->duration_s <= 0) {
            return RuleResult::pass();
        }
        $share = min(1, $s->overlap_s / $s->duration_s);

        return new RuleResult(
            risk: $share >= $params['risk_share'] ? (int) $params['risk'] : 0,
            sessionCap: (int) floor($s->raw_steps * (1 - $share)),
            evidence: ['overlap_s' => $s->overlap_s, 'share' => round($share, 2)],
        );
    }
}
