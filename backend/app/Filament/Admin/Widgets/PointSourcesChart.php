<?php

namespace App\Filament\Admin\Widgets;

use App\Domain\Analytics\Metrics;
use App\Enums\TransactionType;
use Filament\Widgets\ChartWidget;

/** Where points come from and where they go, by ledger type (last 30 days). */
class PointSourcesChart extends ChartWidget
{
    protected ?string $heading = 'منابع و مصارف امتیاز (۳۰ روز)';

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return auth('admin')->user()?->hasAbility('reports.view') ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $e = app(Metrics::class)->economy(30);
        $types = array_values(array_unique([...array_keys($e['issued']), ...array_keys($e['spent'])]));
        $label = fn (string $t) => TransactionType::tryFrom($t)?->label() ?? $t;

        return [
            'labels' => array_map($label, $types),
            'datasets' => [
                ['label' => 'صادرشده', 'data' => array_map(fn ($t) => $e['issued'][$t] ?? 0, $types), 'backgroundColor' => '#1A7F4B'],
                ['label' => 'مصرف‌شده', 'data' => array_map(fn ($t) => $e['spent'][$t] ?? 0, $types), 'backgroundColor' => '#E8A400'],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y', 'scales' => ['x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]];
    }
}
