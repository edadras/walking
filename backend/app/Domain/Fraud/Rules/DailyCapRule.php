<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;

/** A physiological ceiling on verified steps per day (marathon-level ~60k). */
class DailyCapRule extends AbstractRule
{
    public const KEY = 'daily_physiological_cap';

    public function name(): string
    {
        return 'سقف فیزیولوژیک روزانه';
    }

    public function category(): string
    {
        return 'account';
    }

    public function defaults(): array
    {
        return ['max_daily_steps' => 60000, 'risk' => 30];
    }

    public function evaluate(SessionContext $context, array $params): RuleResult
    {
        $room = max(0, (int) $params['max_daily_steps'] - $context->dailyVerifiedBefore);
        if ($context->session->raw_steps <= $room) {
            return RuleResult::pass();
        }

        return new RuleResult(risk: (int) $params['risk'], sessionCap: $room, evidence: ['daily_before' => $context->dailyVerifiedBefore]);
    }
}
