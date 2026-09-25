<?php

namespace App\Filament\Admin\Widgets;

use App\Domain\Analytics\Metrics;
use App\Domain\Wallet\ConversionRate;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Is the points economy balanced? Last 30 days plus today's outstanding liability. */
class EconomyOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth('admin')->user()?->hasAbility('reports.view') ?? false;
    }

    protected function getStats(): array
    {
        $m = app(Metrics::class);
        $e = $m->economy(30);
        $liability = $m->liability();
        $rate = app(ConversionRate::class)->current();
        $fmt = fn (int $n) => number_format($n);
        $held = $liability['available'] + $liability['pending'];
        $burn = $e['issued_total'] > 0 ? round($e['spent_total'] * 100 / $e['issued_total']) : 0;
        $funded = $e['issued_total'] > 0 ? round($e['funded_total'] * 100 / $e['issued_total']) : 0;

        return [
            Stat::make('امتیاز صادرشده (۳۰ روز)', $fmt($e['issued_total']))
                ->description('معادل '.$fmt($e['issued_total'] * $rate).' ریال'),
            Stat::make('امتیاز مصرف‌شده (۳۰ روز)', $fmt($e['spent_total']))
                ->description('نسبت مصرف به صدور: '.$burn.'٪')
                // Under ~40% the balance piles up faster than it is used: rewards may be too generous or the store too thin.
                ->color($burn < 40 ? 'danger' : ($burn < 70 ? 'warning' : 'success')),
            Stat::make('سهم تأمین‌شده توسط اسپانسر/تبلیغ', $funded.'٪')
                ->description($fmt($e['funded_total']).' امتیاز؛ بقیه هزینه پلتفرم است')
                ->color($funded >= 30 ? 'success' : 'gray'),
            Stat::make('بدهی امتیازی فعلی', $fmt($held))
                ->description($fmt($liability['pending']).' در انتظار آزادسازی · '.$fmt($held * $rate).' ریال'),
            Stat::make('منقضی‌شده (۳۰ روز)', $fmt($e['expired_total'])),
        ];
    }
}
