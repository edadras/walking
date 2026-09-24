<?php

namespace App\Filament\Sponsor\Resources\Locations;

use App\Enums\LocationStatus;
use App\Filament\Shared\SponsorForms;
use App\Filament\Sponsor\Concerns\SponsorScoped;
use App\Models\Location;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LocationResource extends Resource
{
    use SponsorScoped;

    protected static ?string $model = Location::class;

    protected static string $ability = 'locations.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'شعبه';

    protected static ?string $modelLabel = 'شعبه';

    protected static ?string $pluralModelLabel = 'شعبه‌ها';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(SponsorForms::location());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('شعبه')->searchable(),
                TextColumn::make('city')->label('شهر'),
                TextColumn::make('radius_m')->label('شعاع')->suffix(' m'),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (LocationStatus $state) => $state->label())
                    ->color(fn (LocationStatus $state) => match ($state) {
                        LocationStatus::Approved => 'success', LocationStatus::Pending => 'warning', default => 'danger'
                    })
                    ->description(fn (Location $r) => $r->status === LocationStatus::Rejected ? $r->rejection_reason : null),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
