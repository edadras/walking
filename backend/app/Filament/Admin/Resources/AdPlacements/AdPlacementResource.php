<?php

namespace App\Filament\Admin\Resources\AdPlacements;

use App\Enums\AdFormat;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\AdPlacement;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AdPlacementResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = AdPlacement::class;

    protected static ?string $viewAbility = 'ads.manage';

    protected static ?string $manageAbility = 'ads.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'تبلیغات';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'جایگاه';

    protected static ?string $pluralModelLabel = 'جایگاه‌های تبلیغ';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('key')->label('کلید (در اپ)')->required()->alphaDash()->maxLength(48)->unique(ignoreRecord: true)->disabledOn('edit'),
            TextInput::make('name')->label('نام')->required()->maxLength(100),
            Select::make('format')->label('قالب')->required()->options(collect(AdFormat::cases())->mapWithKeys(fn ($f) => [$f->value => $f->label()])),
            Select::make('ad_provider_id')->label('شبکه تبلیغاتی')->relationship('provider', 'name')->required(),
            Toggle::make('is_active')->label('فعال')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('key')->label('کلید'),
            TextColumn::make('name')->label('نام'),
            TextColumn::make('format')->label('قالب')->badge()->formatStateUsing(fn (AdFormat $state) => $state->label()),
            TextColumn::make('provider.name')->label('شبکه'),
            IconColumn::make('is_active')->label('فعال')->boolean(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdPlacements::route('/'),
            'create' => Pages\CreateAdPlacement::route('/create'),
            'edit' => Pages\EditAdPlacement::route('/{record}/edit'),
        ];
    }
}
