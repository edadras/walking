<?php

namespace App\Filament\Admin\Widgets;

use App\Domain\Analytics\Metrics;
use App\Models\FraudRule;
use Filament\Widgets\Widget;

class TopFraudRules extends Widget
{
    protected string $view = 'filament.admin.top-fraud-rules';

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth('admin')->user()?->hasAbility('fraud.manage') ?? false;
    }

    /** @return list<array{name: string, events: int, users: int, avg: float}> */
    public function getRows(): array
    {
        $names = FraudRule::query()->pluck('name', 'key');

        return array_map(fn ($r) => ['name' => $names[$r->rule_key] ?? $r->rule_key, 'events' => (int) $r->events, 'users' => (int) $r->users, 'avg' => (float) $r->avg_score],
            app(Metrics::class)->topFraudRules(7));
    }
}
