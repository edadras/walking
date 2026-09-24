<?php

namespace App\Domain\Fraud;

use App\Domain\Fraud\Contracts\FraudRule;
use App\Models\FraudRule as FraudRuleModel;
use Illuminate\Support\Facades\Cache;

/**
 * Code defines which rules exist and their safe defaults; the admin panel
 * (fraud_rules table) enables/disables them and tunes weights and params.
 */
class RuleRegistry
{
    private const CACHE_KEY = 'fraud_rules:v1';

    /** @var list<class-string<FraudRule>> */
    public const RULES = [
        Rules\CadenceCeilingRule::class,
        Rules\ImpossibleRateRule::class,
        Rules\DetectorMismatchRule::class,
        Rules\MotionSignatureRule::class,
        Rules\MetronomeRule::class,
        Rules\VehicleRule::class,
        Rules\GpsTeleportRule::class,
        Rules\MockLocationRule::class,
        Rules\DeviceIntegrityRule::class,
        Rules\ClockSkewRule::class,
        Rules\OverlapRule::class,
        Rules\DailyCapRule::class,
        Rules\MultiAccountDeviceRule::class,
        Rules\MultiDeviceAccountRule::class,
        Rules\RepeatOffenderRule::class,
    ];

    /** @return list<array{rule: FraudRule, weight: int, params: array<string, mixed>}> */
    public function active(): array
    {
        $config = Cache::rememberForever(self::CACHE_KEY, fn () => FraudRuleModel::query()->get()->keyBy('key')->map(fn (FraudRuleModel $r) => [
            'enabled' => $r->is_enabled,
            'weight' => $r->weight,
            'params' => $r->params ?? [],
        ])->all());

        $active = [];
        foreach (self::RULES as $class) {
            $rule = app($class);
            $row = $config[$rule->key()] ?? null;
            if ($row !== null && ! $row['enabled']) {
                continue;
            }
            $active[] = [
                'rule' => $rule,
                'weight' => $row['weight'] ?? 100,
                'params' => array_replace($rule->defaults(), array_intersect_key($row['params'] ?? [], $rule->defaults())),
            ];
        }

        return $active;
    }

    /** Creates missing rows so every rule is visible and tunable in the admin panel. */
    public function sync(): void
    {
        foreach (self::RULES as $class) {
            $rule = app($class);
            $row = FraudRuleModel::query()->firstOrNew(['key' => $rule->key()]);
            $row->fill([
                'name' => $rule->name(),
                'category' => $rule->category(),
                'params' => array_replace($rule->defaults(), array_intersect_key($row->params ?? [], $rule->defaults())),
            ]);
            if (! $row->exists) {
                $row->is_enabled = true;
                $row->weight = 100;
            }
            $row->save();
        }
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
