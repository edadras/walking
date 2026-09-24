<?php

namespace App\Domain\Reward;

use App\Domain\Settings\Settings;
use App\Enums\RewardRuleType;
use App\Models\RewardRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Resolves the admin-defined reward rules in effect at a given moment. */
class RewardRules
{
    private const CACHE_KEY = 'reward_rules:v2';

    public function __construct(private readonly Settings $settings) {}

    /** @return array{steps: int, points: int} */
    public function stepRate(CarbonInterface $at): array
    {
        $rule = $this->first(RewardRuleType::StepRate, $at);

        return ['steps' => max(1, $rule?->steps ?? 1000), 'points' => $rule?->points ?? 10];
    }

    public function maxRewardedSteps(CarbonInterface $at): int
    {
        return $this->first(RewardRuleType::MaxRewardedSteps, $at)?->cap ?? 20000;
    }

    public function dailyCap(CarbonInterface $at): int
    {
        return $this->first(RewardRuleType::DailyCap, $at)?->cap ?? 300;
    }

    public function weeklyCap(CarbonInterface $at): int
    {
        return $this->first(RewardRuleType::WeeklyCap, $at)?->cap ?? 1500;
    }

    public function goalBonus(CarbonInterface $at): int
    {
        return $this->first(RewardRuleType::GoalBonus, $at)?->points ?? 0;
    }

    /** @return array<int, int> streak length → bonus points */
    public function streakBonuses(CarbonInterface $at): array
    {
        $params = $this->first(RewardRuleType::StreakBonus, $at)?->params ?? [];

        return collect($params)->mapWithKeys(fn ($points, $days) => [(int) $days => (int) $points])->all();
    }

    /**
     * Multipliers active at a local moment (weekday / bonus hours / date window),
     * multiplied together and capped by `reward.max_multiplier`.
     *
     * @return array{factor: float, applied: list<array{name: string, multiplier: float}>}
     */
    public function multiplier(CarbonInterface $localTime): array
    {
        $applied = [];
        $factor = 1.0;
        foreach ($this->active(RewardRuleType::Multiplier, $localTime) as $rule) {
            if ($rule->days() !== [] && ! in_array($localTime->dayOfWeek, $rule->days(), true)) {
                continue;
            }
            if ($rule->start_time && $rule->end_time) {
                $t = $localTime->format('H:i:s');
                if ($t < $rule->start_time || $t >= $rule->end_time) {
                    continue;
                }
            }
            $factor *= (float) $rule->multiplier;
            $applied[] = ['name' => $rule->name, 'multiplier' => (float) $rule->multiplier];
        }

        return ['factor' => min($factor, (float) $this->settings->get('reward.max_multiplier', 3)), 'applied' => $applied];
    }

    /** @return Collection<int, RewardRule> */
    private function active(RewardRuleType $type, CarbonInterface $at): Collection
    {
        return $this->all()
            ->filter(fn (RewardRule $r) => $r->rule_type === $type)
            ->filter(fn (RewardRule $r) => ($r->starts_at === null || $r->starts_at->lte($at)) && ($r->ends_at === null || $r->ends_at->gt($at)))
            ->sortByDesc('priority')
            ->values();
    }

    private function first(RewardRuleType $type, CarbonInterface $at): ?RewardRule
    {
        return $this->active($type, $at)->first();
    }

    /** @return Collection<int, RewardRule> */
    private function all(): Collection
    {
        // Cache raw attributes, not models: object unserialization from cache is disabled.
        $rows = Cache::remember(self::CACHE_KEY, 300, fn () => RewardRule::query()->where('is_active', true)->get()->map->getAttributes()->all());

        return RewardRule::hydrate($rows)->toBase();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
