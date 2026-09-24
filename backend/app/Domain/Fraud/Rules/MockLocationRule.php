<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;

class MockLocationRule extends AbstractRule
{
    public const KEY = 'mock_location';

    public function name(): string
    {
        return 'موقعیت جعلی (Mock Location)';
    }

    public function category(): string
    {
        return 'device';
    }

    public function defaults(): array
    {
        return ['risk' => 70];
    }

    public function evaluate(SessionContext $context, array $params): RuleResult
    {
        $mock = (bool) $context->motion('mock_location', false) || (bool) $context->gps('mock_detected', false);

        return $mock ? new RuleResult(risk: (int) $params['risk'], evidence: ['mock' => true]) : RuleResult::pass();
    }
}
