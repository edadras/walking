<?php

namespace App\Filament\Admin\Widgets;

use App\Domain\Analytics\Metrics;
use App\Filament\Shared\TrendChart;

class StoreSalesChart extends TrendChart
{
    protected ?string $heading = 'فروشگاه: سفارش‌ها';

    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return auth('admin')->user()?->hasAbility('orders.manage') || auth('admin')->user()?->hasAbility('reports.view');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function series(Metrics $metrics, int $days): array
    {
        return ['سفارش' => $metrics->orders($days)['orders']];
    }
}
