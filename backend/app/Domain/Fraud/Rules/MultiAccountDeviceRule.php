<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;

class MultiAccountDeviceRule extends AbstractRule
{
    public const KEY = 'multi_account_device';

    public function name(): string
    {
        return 'چند حساب روی یک دستگاه';
    }

    public function category(): string
    {
        return 'account';
    }

    public function defaults(): array
    {
        return ['max_accounts' => 2, 'risk' => 50];
    }

    public function evaluate(SessionContext $context, array $params): RuleResult
    {
        return $context->accountsOnDevice > $params['max_accounts']
            ? new RuleResult(risk: (int) $params['risk'], evidence: ['accounts' => $context->accountsOnDevice])
            : RuleResult::pass();
    }
}
