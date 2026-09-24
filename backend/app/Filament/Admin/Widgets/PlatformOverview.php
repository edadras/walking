<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\FraudCaseStatus;
use App\Enums\TransactionStatus;
use App\Enums\UserStatus;
use App\Models\DailyActivity;
use App\Models\Device;
use App\Models\FraudCase;
use App\Models\PointTransaction;
use App\Models\User;
use App\Models\WalkingSession;
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
            'rejected_today' => (int) WalkingSession::query()->where('local_date', now('Asia/Tehran')->toDateString())->selectRaw('COALESCE(SUM(raw_steps - COALESCE(verified_steps, raw_steps)),0) s')->value('s'),
            'issued_today' => (int) PointTransaction::query()->where('amount', '>', 0)->where('status', '!=', TransactionStatus::Reversed)->where('created_at', '>=', now()->startOfDay())->sum('amount'),
            'spent_today' => (int) -PointTransaction::query()->where('amount', '<', 0)->where('created_at', '>=', now()->startOfDay())->sum('amount'),
            'open_cases' => FraudCase::query()->whereIn('status', [FraudCaseStatus::Open, FraudCaseStatus::Flagged])->count(),
        ]);

        $fmt = fn (int $n) => number_format($n);

        return [
            Stat::make('کل کاربران', $fmt($stats['total']))->description($fmt($stats['active']).' فعال'),
            Stat::make('کاربران فعال روزانه (DAU)', $fmt($stats['dau'])),
            Stat::make('کاربران فعال ماهانه (MAU)', $fmt($stats['mau'])),
            Stat::make('ثبت‌نام امروز', $fmt($stats['new_today'])),
            Stat::make('قدم‌های امروز', $fmt($stats['steps_today']))->description($fmt($stats['verified_today']).' تأییدشده · '.$fmt($stats['rejected_today']).' حذف‌شده'),
            Stat::make('امتیاز صادرشده امروز', $fmt($stats['issued_today']))->description($fmt($stats['spent_today']).' مصرف‌شده'),
            Stat::make('پرونده‌های باز تقلب', $fmt($stats['open_cases']))->color($stats['open_cases'] > 0 ? 'warning' : 'success'),
            Stat::make('دستگاه‌ها', $fmt($stats['devices']))->description($fmt($stats['low_trust']).' با اعتماد پایین')
                ->color($stats['low_trust'] > 0 ? 'warning' : 'success'),
        ];
    }
}
