<?php

namespace App\Filament\Admin\Resources\Challenges;

use App\Enums\ChallengeStatus;
use App\Enums\ChallengeType;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\Challenge;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ChallengeResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = Challenge::class;

    protected static ?string $viewAbility = 'challenges.manage';

    protected static ?string $manageAbility = 'challenges.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'تعامل';

    protected static ?string $modelLabel = 'چالش';

    protected static ?string $pluralModelLabel = 'چالش‌ها';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label('عنوان')->required()->maxLength(150)->columnSpanFull(),
            Textarea::make('description')->label('توضیح')->rows(3)->columnSpanFull(),
            Select::make('type')->label('نوع')->required()->live()->options(collect(ChallengeType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
            Select::make('metric')->label('معیار')->required()->options(['steps' => 'قدم تأییدشده', 'distance' => 'مسافت (متر)', 'visits' => 'بازدید مکان']),
            TextInput::make('target_value')->label('هدف')->numeric()->integer()->minValue(1)->required(),
            TextInput::make('reward_points')->label('پاداش امتیاز')->numeric()->integer()->minValue(0)->default(0),
            TextInput::make('reward_xp')->label('پاداش XP')->numeric()->integer()->minValue(0)->default(0),
            TextInput::make('max_participants')->label('حداکثر شرکت‌کننده')->numeric()->integer()->minValue(1),
            DateTimePicker::make('starts_at')->label('شروع')->required(),
            DateTimePicker::make('ends_at')->label('پایان')->required()->after('starts_at'),
            DateTimePicker::make('join_until')->label('مهلت پیوستن')->before('ends_at'),
            Select::make('status')->label('وضعیت')->required()->default(ChallengeStatus::Draft->value)
                ->options(collect(ChallengeStatus::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
            FileUpload::make('image_path')->label('تصویر')->image()->disk('public')->directory('challenges')->maxSize(1024)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable(),
                TextColumn::make('type')->label('نوع')->badge()->formatStateUsing(fn (ChallengeType $state) => $state->label()),
                TextColumn::make('target_value')->label('هدف')->numeric(),
                TextColumn::make('reward_points')->label('پاداش')->numeric(),
                TextColumn::make('participants_count')->label('شرکت‌کننده')->numeric(),
                TextColumn::make('completed')->label('تکمیل')->state(fn (Challenge $r) => $r->participants()->whereNotNull('completed_at')->count()),
                TextColumn::make('starts_at')->label('شروع')->date(),
                TextColumn::make('ends_at')->label('پایان')->date(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (ChallengeStatus $state) => $state->label()),
            ])
            ->filters([SelectFilter::make('status')->label('وضعیت')->options(collect(ChallengeStatus::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]))])
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
