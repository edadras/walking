<?php

namespace App\Filament\Admin\Widgets;

use App\Domain\Analytics\Metrics;
use App\Filament\Shared\TrendChart;

class PointsFlowChart extends TrendChart
{
    protected ?string $heading = 'جریان امتیاز: صادرشده و مصرف‌شده';

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth('admin')->user()?->hasAbility('wallet.view') || auth('admin')->user()?->hasAbility('reports.view');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function series(Metrics $metrics, int $days): array
    {
        $p = $metrics->points($days);

        return ['صادرشده' => $p['issued'], 'مصرف‌شده' => $p['spent']];
    }
}
