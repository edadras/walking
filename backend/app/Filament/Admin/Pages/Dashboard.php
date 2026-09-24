<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\PlatformOverview;
use App\Filament\Admin\Widgets\PointsFlowChart;
use App\Filament\Admin\Widgets\StepsTrendChart;
use App\Filament\Admin\Widgets\StoreSalesChart;
use App\Filament\Admin\Widgets\UsersTrendChart;
use Filament\Pages\Dashboard as BaseDashboard;

/** Platform dashboard; fraud widgets live on their own page. */
class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [PlatformOverview::class, UsersTrendChart::class, StepsTrendChart::class, PointsFlowChart::class, StoreSalesChart::class];
    }
}
