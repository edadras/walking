<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\UserStatus;
use App\Models\DailyActivity;
use App\Models\Device;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class PlatformOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $stats = Cache::remember('admin:platform-overview', 60, fn () => [
            'total' => User::query()->count(),
            'active' => User::query()->where('status', UserStatus::Active)->count(),
            'dau' => User::query()->where('last_active_at', '>=', now()->subDay())->count(),
            'mau' => User::query()->where('last_active_at', '>=', now()->subDays(30))->count(),
            'new_today' => User::query()->where('created_at', '>=', now()->startOfDay())->count(),
            'devices' => Device::query()->count(),
            'low_trust' => Device::query()->where('trust_score', '<', 30)->count(),
            // Tehran calendar day; per-user timezones make this an approximation for foreign users.
            'steps_today' => (int) DailyActivity::query()->where('local_date', now('Asia/Tehran')->toDateString())->sum('raw_steps'),
            'verified_today' => (int) DailyActivity::query()->where('local_date', now('Asia/Tehran')->toDateString())->sum('verified_steps'),
        ]);

        $fmt = fn (int $n) => number_format($n);

        return [
            Stat::make('کل کاربران', $fmt($stats['total']))->description($fmt($stats['active']).' فعال'),
            Stat::make('کاربران فعال روزانه (DAU)', $fmt($stats['dau'])),
            Stat::make('کاربران فعال ماهانه (MAU)', $fmt($stats['mau'])),
            Stat::make('ثبت‌نام امروز', $fmt($stats['new_today'])),
            Stat::make('قدم‌های امروز', $fmt($stats['steps_today']))->description($fmt($stats['verified_today']).' تأییدشده'),
            Stat::make('دستگاه‌ها', $fmt($stats['devices']))->description($fmt($stats['low_trust']).' با اعتماد پایین')
                ->color($stats['low_trust'] > 0 ? 'warning' : 'success'),
        ];
    }
}
