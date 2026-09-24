<?php

namespace App\Filament\Admin\Widgets;

use App\Domain\Analytics\Metrics;
use App\Filament\Shared\TrendChart;

class UsersTrendChart extends TrendChart
{
    protected ?string $heading = 'کاربران فعال و ثبت‌نام';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth('admin')->user()?->hasAbility('reports.view') ?? false;
    }

    protected function series(Metrics $metrics, int $days): array
    {
        return ['کاربر فعال (قدم ثبت‌شده)' => $metrics->activeUsers($days), 'ثبت‌نام جدید' => $metrics->newUsers($days)];
    }
}
