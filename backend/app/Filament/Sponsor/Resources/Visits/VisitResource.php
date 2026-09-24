<?php

namespace App\Filament\Sponsor\Resources\Visits;

use App\Enums\VisitStatus;
use App\Filament\Sponsor\Concerns\SponsorScoped;
use App\Models\Campaign;
use App\Models\Visit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Visits to the sponsor's branches — without any user identity (privacy). */
class VisitResource extends Resource
{
    use SponsorScoped;

    protected static ?string $model = Visit::class;

    protected static string $ability = 'visits.view';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'گزارش';

    protected static ?string $modelLabel = 'بازدید';

    protected static ?string $pluralModelLabel = 'بازدیدها';

    public static function getEloquentQuery(): Builder
    {
        return Visit::query()->whereIn('campaign_id', Campaign::query()->where('sponsor_id', static::sponsorId())->select('id'));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('زمان')->dateTime(),
                TextColumn::make('campaign.name')->label('کمپین'),
                TextColumn::make('location.name')->label('شعبه'),
                TextColumn::make('stay_seconds')->label('مدت حضور')->formatStateUsing(fn (int $state) => intdiv($state, 60).' دقیقه'),
                TextColumn::make('points_awarded')->label('امتیاز')->numeric(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (VisitStatus $state) => $state->label()),
            ])
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(collect(VisitStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                SelectFilter::make('location_id')->label('شعبه')->relationship('location', 'name', fn (Builder $query) => $query->where('sponsor_id', static::sponsorId())),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListVisits::route('/')];
    }
}
