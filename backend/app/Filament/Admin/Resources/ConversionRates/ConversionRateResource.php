<?php

namespace App\Filament\Admin\Resources\ConversionRates;

use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\Admin;
use App\Models\PointConversionRate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** History is append-only: a new rate never rewrites past transactions (each keeps its snapshot). */
class ConversionRateResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = PointConversionRate::class;

    protected static ?string $viewAbility = 'rewards.manage';

    protected static ?string $manageAbility = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'امتیاز و کیف پول';

    protected static ?string $modelLabel = 'نرخ تبدیل';

    protected static ?string $pluralModelLabel = 'نرخ تبدیل امتیاز';

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('rial_per_point')->label('ریال به ازای هر امتیاز')->numeric(),
                TextColumn::make('effective_from')->label('از تاریخ')->dateTime(),
                TextColumn::make('created_by')->label('ثبت توسط')->formatStateUsing(fn ($state) => Admin::query()->find($state)?->name)->placeholder('سیستم'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListConversionRates::route('/')];
    }
}
