<?php

namespace App\Filament\Org\Widgets;

use App\Domain\Organization\OrganizationService;
use App\Models\OrganizationUser;
use App\Support\Jalali;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrgOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $user = auth('org')->user();
        if (! $user instanceof OrganizationUser) {
            return [];
        }
        $org = $user->organization;
        $s = app(OrganizationService::class)->stats($org);
        $fmt = fn (?int $n) => $n === null ? '—' : number_format($n);

        return [
            Stat::make('اعضا', $fmt($s['members']).' / '.$fmt($s['seats']))->description('کد عضویت: '.$org->join_code),
            Stat::make('مشارکت ۷ روز اخیر', $s['participation'].'٪')->description($fmt($s['active_7d']).' نفر فعال'),
            Stat::make('میانگین قدم روزانه هر نفر', $fmt($s['avg_daily_steps']))
                ->description($s['avg_daily_steps'] === null ? 'برای حفظ حریم خصوصی، کمتر از '.OrganizationService::MIN_GROUP.' عضو نمایش داده نمی‌شود' : '۷ روز اخیر، فقط قدم‌های تأییدشده'),
            Stat::make('اعتبار اشتراک', $org->paid_until ? Jalali::format($org->paid_until) : 'پرداخت نشده')
                ->color($org->isActive() ? 'success' : 'danger'),
        ];
    }
}
