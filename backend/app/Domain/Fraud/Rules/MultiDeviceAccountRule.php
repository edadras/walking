<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;

class MultiDeviceAccountRule extends AbstractRule
{
    public const KEY = 'multi_device_account';

    public function name(): string
    {
        return 'چند دستگاه فعال برای یک حساب';
    }

    public function category(): string
    {
        return 'account';
    }

    public function defaults(): array
    {
        return ['max_devices' => 3, 'risk' => 30];
    }

    public function evaluate(SessionContext $context, array $params): RuleResult
    {
        return $context->activeDevicesForUser > $params['max_devices']
            ? new RuleResult(risk: (int) $params['risk'], evidence: ['devices_24h' => $context->activeDevicesForUser])
            : RuleResult::pass();
    }
}
