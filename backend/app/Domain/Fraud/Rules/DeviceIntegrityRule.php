<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;
use App\Enums\IntegrityVerdict;

/**
 * Play Integrity (decoded server-side) and weak client hints. Devices without
 * an integrity verdict — common in Iran without Play services — are not
 * treated as fraud, but their daily rewarded steps are capped lower.
 */
class DeviceIntegrityRule extends AbstractRule
{
    public const KEY = 'device_integrity';

    public function name(): string
    {
        return 'سلامت دستگاه (Play Integrity، روت، شبیه‌ساز)';
    }

    public function category(): string
    {
        return 'device';
    }

    public function defaults(): array
    {
        return ['risk_none' => 30, 'risk_unavailable' => 5, 'risk_emulator' => 20, 'risk_root' => 15, 'unavailable_daily_cap' => 20000];
    }

    public function evaluate(SessionContext $context, array $params): RuleResult
    {
        $d = $context->device;
        $risk = match ($d->integrity_verdict) {
            IntegrityVerdict::None => $params['risk_none'],
            IntegrityVerdict::Unavailable, null => $params['risk_unavailable'],
            default => 0,
        };
        $risk += $d->emulator_suspected ? $params['risk_emulator'] : 0;
        $risk += $d->root_suspected ? $params['risk_root'] : 0;

        $cap = null;
        if (in_array($d->integrity_verdict, [IntegrityVerdict::Unavailable, IntegrityVerdict::None, null], true)) {
            $cap = max(0, (int) $params['unavailable_daily_cap'] - $context->dailyVerifiedBefore);
            if ($cap >= $context->session->raw_steps) {
                $cap = null;
            }
        }

        return new RuleResult(
            risk: (int) $risk,
            sessionCap: $cap,
            evidence: $risk > 0 || $cap !== null ? ['integrity' => $d->integrity_verdict?->value, 'emulator' => $d->emulator_suspected, 'root' => $d->root_suspected] : [],
        );
    }
}
