<?php

namespace App\Filament\Admin\Resources\Visits;

use App\Enums\VisitStatus;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\Visit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class VisitResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = Visit::class;

    protected static ?string $viewAbility = 'sponsors.manage';

    protected static ?string $manageAbility = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'اسپانسرها';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'بازدید';

    protected static ?string $pluralModelLabel = 'بازدیدها';

    public static function canViewAny(): bool
    {
        return static::allows('sponsors.manage') || static::allows('fraud.manage');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('user.phone')->label('کاربر')->searchable(),
                TextColumn::make('campaign.name')->label('کمپین'),
                TextColumn::make('location.name')->label('شعبه'),
                TextColumn::make('stay_seconds')->label('حضور (ثانیه)')->numeric(),
                TextColumn::make('pings_count')->label('Ping'),
                TextColumn::make('min_distance_m')->label('کمترین فاصله')->suffix(' m'),
                TextColumn::make('best_accuracy_m')->label('بهترین دقت')->suffix(' m'),
                TextColumn::make('qr_verified_at')->label('QR')->since()->placeholder('—'),
                TextColumn::make('points_awarded')->label('امتیاز')->numeric(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (VisitStatus $state) => $state->label())
                    ->color(fn (VisitStatus $state) => match ($state) {
                        VisitStatus::Rewarded => 'success', VisitStatus::Rejected => 'danger', default => 'gray'
                    }),
                TextColumn::make('rejection_reason')->label('دلیل'),
                TextColumn::make('created_at')->label('زمان')->since(),
            ])
            ->filters([SelectFilter::make('status')->label('وضعیت')->options(collect(VisitStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListVisits::route('/')];
    }
}
