<?php

namespace App\Filament\Sponsor\Widgets;

use App\Enums\CampaignStatus;
use App\Enums\SponsorStatus;
use App\Enums\VisitStatus;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\SponsorUser;
use App\Models\Visit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SponsorOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $user = auth('sponsor')->user();
        if (! $user instanceof SponsorUser) {
            return [];
        }
        $sponsor = $user->sponsor;
        if ($sponsor->status !== SponsorStatus::Approved) {
            return [
                Stat::make('وضعیت حساب', $sponsor->status->label())
                    ->description($sponsor->rejection_reason ?? 'پس از تأیید تیم گام‌یار می‌توانید شعبه و کمپین ثبت کنید.')
                    ->color($sponsor->status === SponsorStatus::Pending ? 'warning' : 'danger'),
            ];
        }

        $campaignIds = Campaign::query()->where('sponsor_id', $sponsor->id)->pluck('id');
        $rewarded = Visit::query()->whereIn('campaign_id', $campaignIds)->where('status', VisitStatus::Rewarded);
        $fmt = fn (int $n) => number_format($n);

        return [
            Stat::make('بودجه باقی‌مانده (امتیاز)', $fmt($sponsor->budgetRemaining()))->description($fmt($sponsor->points_spent).' مصرف‌شده از '.$fmt($sponsor->point_budget))
                ->color($sponsor->budgetRemaining() < max(100, $sponsor->point_budget * 0.1) ? 'warning' : 'success'),
            Stat::make('بازدید تأییدشده امروز', $fmt((clone $rewarded)->where('verified_at', '>=', now('Asia/Tehran')->startOfDay()->utc())->count())),
            Stat::make('بازدید تأییدشده ۳۰ روز', $fmt((clone $rewarded)->where('verified_at', '>=', now()->subDays(30))->count())),
            Stat::make('کمپین فعال', $fmt(Campaign::query()->where('sponsor_id', $sponsor->id)->where('status', CampaignStatus::Active)->count())),
            Stat::make('کوپن استفاده‌شده', $fmt((int) Coupon::query()->where('sponsor_id', $sponsor->id)->sum('redeemed_count')))
                ->description($fmt((int) Coupon::query()->where('sponsor_id', $sponsor->id)->sum('claimed_count')).' صادرشده'),
        ];
    }
}
