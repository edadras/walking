<?php

namespace App\Filament\Sponsor\Widgets;

use App\Domain\Analytics\Metrics;

class AdsChart extends SponsorTrendChart
{
    protected ?string $heading = 'تبلیغات: نمایش و کلیک';

    protected static ?int $sort = 4;

    protected function series(Metrics $metrics, int $days): array
    {
        $a = $metrics->ads($days, $this->sponsorId());

        return ['نمایش' => $a['impressions'], 'کلیک' => $a['clicks']];
    }
}
