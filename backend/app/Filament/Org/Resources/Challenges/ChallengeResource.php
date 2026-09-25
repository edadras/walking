<?php

namespace App\Filament\Org\Resources\Challenges;

use App\Enums\ChallengeStatus;
use App\Enums\ChallengeType;
use App\Models\Challenge;
use App\Models\OrganizationUser;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Company-only challenges. They award XP, never platform points (points are money). */
class ChallengeResource extends Resource
{
    protected static ?string $model = Challenge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $modelLabel = 'چالش سازمانی';

    protected static ?string $pluralModelLabel = 'چالش‌های سازمانی';

    protected static ?string $slug = 'challenges';

    private static function user(): ?OrganizationUser
    {
        $u = auth('org')->user();

        return $u instanceof OrganizationUser ? $u : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('organization_id', self::user()?->organization_id ?? 0);
    }

    public static function canCreate(): bool
    {
        return (bool) self::user()?->isAdmin() && self::user()->organization->isActive();
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) self::user()?->isAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label('عنوان')->required()->maxLength(120)->columnSpanFull(),
            Textarea::make('description')->label('توضیح')->maxLength(500)->columnSpanFull(),
            Select::make('metric')->label('معیار')->options(['steps' => 'قدم', 'distance' => 'مسافت (متر)'])->required()->live(),
            TextInput::make('target_value')->label('هدف')->numeric()->integer()->minValue(1000)->maxValue(10_000_000)->required(),
            DateTimePicker::make('starts_at')->label('شروع')->required()->default(now()),
            DateTimePicker::make('ends_at')->label('پایان')->required()->after('starts_at'),
            TextInput::make('reward_xp')->label('XP پاداش')->numeric()->integer()->minValue(0)->maxValue(500)->default(100),
            Select::make('status')->label('وضعیت')->options([ChallengeStatus::Active->value => 'فعال', ChallengeStatus::Cancelled->value => 'لغو'])->default(ChallengeStatus::Active->value)->required(),
        ]);
    }

    /** Server-side values the form can't influence. */
    public static function scoped(array $data): array
    {
        return [
            ...$data,
            'organization_id' => self::user()->organization_id,
            'type' => ($data['metric'] ?? 'steps') === 'distance' ? ChallengeType::Distance->value : ChallengeType::Steps->value,
            'reward_points' => 0,
            'reward_xp' => min(500, (int) ($data['reward_xp'] ?? 0)),
            'created_by_type' => 'organization_user',
            'created_by_id' => self::user()->id,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ends_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable(),
                TextColumn::make('target_value')->label('هدف')->numeric(),
                TextColumn::make('participants_count')->label('شرکت‌کننده')->numeric(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (ChallengeStatus $state) => $state->label()),
                TextColumn::make('ends_at')->label('پایان')->dateTime(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChallenges::route('/'),
            'create' => Pages\CreateChallenge::route('/create'),
            'edit' => Pages\EditChallenge::route('/{record}/edit'),
        ];
    }
}
