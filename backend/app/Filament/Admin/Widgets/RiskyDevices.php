<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Device;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Devices shared by several accounts or with low trust — the usual farming signals. */
class RiskyDevices extends TableWidget
{
    protected static ?string $heading = 'دستگاه‌های پرریسک';

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth('admin')->user()?->hasAbility('fraud.manage') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Device::query()->withCount('users')->where(fn ($q) => $q->where('trust_score', '<', 40)->orWhere('root_suspected', true)->orHas('users', '>', 1)))
            ->defaultSort('trust_score')
            ->paginated([5])
            ->emptyStateHeading('دستگاه پرریسکی پیدا نشد.')
            ->columns([
                TextColumn::make('model')->label('مدل')->formatStateUsing(fn ($record) => trim($record->manufacturer.' '.$record->model)),
                TextColumn::make('trust_score')->label('اعتماد')->color(fn (int $state) => $state < 40 ? 'danger' : null),
                TextColumn::make('users_count')->label('حساب‌ها')->color(fn (int $state) => $state > 1 ? 'danger' : null),
                IconColumn::make('root_suspected')->label('روت')->boolean(),
            ]);
    }
}
