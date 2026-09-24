<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\FraudCaseStatus;
use App\Enums\UserStatus;
use App\Models\FraudCase;
use App\Models\FraudEvent;
use App\Models\User;
use App\Models\WalkingSession;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FraudOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth('admin')->user()?->hasAbility('fraud.manage') ?? false;
    }

    protected function getStats(): array
    {
        $today = now('Asia/Tehran')->toDateString();
        $sessions = WalkingSession::query()->where('local_date', $today);
        $raw = (int) (clone $sessions)->sum('raw_steps');
        $removed = (int) (clone $sessions)->selectRaw('COALESCE(SUM(raw_steps - COALESCE(verified_steps, raw_steps)),0) s')->value('s');
        $fmt = fn (int $n) => number_format($n);

        return [
            Stat::make('پرونده‌های باز', $fmt(FraudCase::query()->whereIn('status', [FraudCaseStatus::Open, FraudCaseStatus::Flagged])->count()))
                ->url(route('filament.admin.resources.fraud-cases.index')),
            Stat::make('جلسه‌های در انتظار بررسی', $fmt((clone $sessions)->where('status', 'review')->count())),
            Stat::make('قدم حذف‌شده امروز', $fmt($removed))->description($raw > 0 ? round($removed * 100 / $raw, 1).'٪ از کل' : '—'),
            Stat::make('سیگنال‌های ۲۴ ساعت', $fmt(FraudEvent::query()->where('created_at', '>=', now()->subDay())->count())),
            Stat::make('کاربران مسدود', $fmt(User::query()->where('status', UserStatus::Banned)->count())),
        ];
    }
}
