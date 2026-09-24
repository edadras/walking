<?php

namespace App\Filament\Sponsor\Widgets;

use App\Domain\Analytics\Metrics;

class CouponsChart extends SponsorTrendChart
{
    protected ?string $heading = 'کوپن‌ها: صادرشده و استفاده‌شده';

    protected static ?int $sort = 3;

    protected function series(Metrics $metrics, int $days): array
    {
        $c = $metrics->coupons($days, $this->sponsorId());

        return ['استفاده‌شده' => $c['redeemed'], 'صادرشده' => $c['claimed']];
    }
}
