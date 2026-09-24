<?php

namespace App\Filament\Admin\Resources\Locations;

use App\Enums\LocationStatus;
use App\Filament\Admin\Concerns\ModerationActions;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Filament\Shared\SponsorForms;
use App\Models\Location;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class LocationResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = Location::class;

    protected static ?string $viewAbility = 'sponsors.manage';

    protected static ?string $manageAbility = 'sponsors.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'اسپانسرها';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'شعبه';

    protected static ?string $pluralModelLabel = 'شعبه‌ها';

    public static function getNavigationBadge(): ?string
    {
        $n = Location::query()->where('status', LocationStatus::Pending)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function canCreate(): bool
    {
        return false; // sponsors create their branches
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(SponsorForms::location());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('شعبه')->searchable(),
                TextColumn::make('sponsor.name')->label('اسپانسر')->searchable(),
                TextColumn::make('city')->label('شهر'),
                TextColumn::make('radius_m')->label('شعاع')->suffix(' m'),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (LocationStatus $state) => $state->label())
                    ->color(fn (LocationStatus $state) => match ($state) {
                        LocationStatus::Approved => 'success', LocationStatus::Pending => 'warning', default => 'gray'
                    }),
                TextColumn::make('rejection_reason')->label('دلیل رد')->limit(30)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('ثبت')->since(),
            ])
            ->filters([SelectFilter::make('status')->label('وضعیت')->options(collect(LocationStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))])
            ->recordActions([
                Action::make('map')->label('نقشه')->icon('heroicon-o-map')->color('gray')
                    ->url(fn (Location $r) => "https://www.openstreetmap.org/?mlat={$r->latitude}&mlon={$r->longitude}#map=18/{$r->latitude}/{$r->longitude}", true),
                EditAction::make(),
                ...ModerationActions::make(fn (Location $r) => $r->status === LocationStatus::Pending),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
