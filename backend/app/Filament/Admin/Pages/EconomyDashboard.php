<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\EconomyOverview;
use App\Filament\Admin\Widgets\PointsFlowChart;
use App\Filament\Admin\Widgets\PointSourcesChart;
use App\Models\Admin;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class EconomyDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'امتیاز و کیف پول';

    protected static ?int $navigationSort = -1;

    protected static ?string $navigationLabel = 'تعادل اقتصاد امتیاز';

    protected static ?string $title = 'تعادل اقتصاد امتیاز';

    protected static ?string $slug = 'economy';

    public static function canAccess(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof Admin && $admin->hasAbility('reports.view');
    }

    protected function getHeaderWidgets(): array
    {
        return [EconomyOverview::class, PointsFlowChart::class, PointSourcesChart::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
