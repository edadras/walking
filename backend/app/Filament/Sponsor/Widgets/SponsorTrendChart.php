<?php

namespace App\Filament\Sponsor\Widgets;

use App\Filament\Shared\TrendChart;
use App\Models\SponsorUser;

abstract class SponsorTrendChart extends TrendChart
{
    public static function canView(): bool
    {
        $user = auth('sponsor')->user();

        return $user instanceof SponsorUser && $user->hasAbility('analytics.view');
    }

    protected function sponsorId(): int
    {
        return auth('sponsor')->user()->sponsor_id;
    }
}
