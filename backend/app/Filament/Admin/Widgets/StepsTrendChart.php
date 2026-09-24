<?php

namespace App\Filament\Admin\Widgets;

use App\Domain\Analytics\Metrics;
use App\Filament\Shared\TrendChart;

class StepsTrendChart extends TrendChart
{
    protected ?string $heading = 'قدم‌ها: ثبت‌شده و تأییدشده';

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth('admin')->user()?->hasAbility('reports.view') ?? false;
    }

    protected function series(Metrics $metrics, int $days): array
    {
        $s = $metrics->steps($days);

        return ['تأییدشده' => $s['verified'], 'ثبت‌شده' => $s['raw']];
    }
}
