<?php

namespace App\Filament\Sponsor\Widgets;

use App\Domain\Analytics\Metrics;

class VisitsChart extends SponsorTrendChart
{
    protected ?string $heading = 'بازدیدهای شعبه‌ها';

    protected static ?int $sort = 2;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function series(Metrics $metrics, int $days): array
    {
        $v = $metrics->visits($days, $this->sponsorId());

        return ['تأییدشده' => $v['rewarded'], 'ردشده' => $v['rejected']];
    }
}
