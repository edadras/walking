<?php

namespace App\Filament\Admin\Widgets;

use App\Domain\Analytics\Metrics;
use App\Filament\Shared\TrendChart;

class SessionVerdictsChart extends TrendChart
{
    protected ?string $heading = 'نتیجه بررسی جلسه‌های پیاده‌روی';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth('admin')->user()?->hasAbility('fraud.manage') ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function series(Metrics $metrics, int $days): array
    {
        $s = $metrics->sessionsByStatus($days);

        return ['تأییدشده' => $s['verified'], 'بررسی دستی' => $s['review'], 'رد' => $s['rejected'], 'تأیید جزئی' => $s['partially_verified']];
    }
}
