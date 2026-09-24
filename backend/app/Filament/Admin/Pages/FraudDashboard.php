<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\FraudOverview;
use App\Filament\Admin\Widgets\RiskyDevices;
use App\Filament\Admin\Widgets\SessionVerdictsChart;
use App\Filament\Admin\Widgets\TopFraudRules;
use App\Models\Admin;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class FraudDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = 'ضد تقلب';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'داشبورد تقلب';

    protected static ?string $title = 'داشبورد ضد تقلب';

    protected static ?string $slug = 'fraud';

    public static function canAccess(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof Admin && $admin->hasAbility('fraud.manage');
    }

    protected function getHeaderWidgets(): array
    {
        return [FraudOverview::class, SessionVerdictsChart::class, TopFraudRules::class, RiskyDevices::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
