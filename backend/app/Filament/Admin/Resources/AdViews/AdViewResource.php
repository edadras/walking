<?php

namespace App\Filament\Admin\Resources\AdViews;

use App\Enums\AdViewStatus;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\AdView;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AdViewResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = AdView::class;

    protected static ?string $viewAbility = 'ads.manage';

    protected static ?string $manageAbility = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlayCircle;

    protected static string|UnitEnum|null $navigationGroup = 'تبلیغات';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'تماشای جایزه‌دار';

    protected static ?string $pluralModelLabel = 'تماشاهای جایزه‌دار';

    public static function canViewAny(): bool
    {
        return static::allows('ads.manage') || static::allows('fraud.manage');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('user.phone')->label('کاربر')->searchable(),
                TextColumn::make('campaign.name')->label('کمپین'),
                TextColumn::make('provider.name')->label('شبکه'),
                TextColumn::make('started_at')->label('شروع')->dateTime(),
                TextColumn::make('duration')->label('مدت (ثانیه)')->state(fn (AdView $r) => $r->completed_at ? (int) $r->started_at->diffInSeconds($r->completed_at, true) : null),
                TextColumn::make('points_awarded')->label('امتیاز')->numeric(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (AdViewStatus $state) => $state->label()),
                TextColumn::make('rejection_reason')->label('دلیل'),
            ])
            ->filters([SelectFilter::make('status')->label('وضعیت')->options(collect(AdViewStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAdViews::route('/')];
    }
}
