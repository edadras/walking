<?php

namespace App\Filament\Admin\Resources\Achievements;

use App\Domain\Gamification\AchievementService;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\Achievement;
use App\Models\UserAchievement;
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

class AchievementResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = Achievement::class;

    protected static ?string $viewAbility = 'challenges.manage';

    protected static ?string $manageAbility = 'challenges.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'تعامل';

    protected static ?string $modelLabel = 'دستاورد';

    protected static ?string $pluralModelLabel = 'دستاوردها';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('key')->label('کلید')->required()->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('name')->label('نام')->required(),
            TextInput::make('description')->label('توضیح')->required()->columnSpanFull(),
            Select::make('metric')->label('معیار')->required()->options(AchievementService::METRICS),
            TextInput::make('threshold')->label('آستانه')->numeric()->integer()->minValue(1)->required(),
            TextInput::make('xp_reward')->label('XP')->numeric()->integer()->minValue(0)->default(0),
            TextInput::make('point_reward')->label('امتیاز')->numeric()->integer()->minValue(0)->default(0),
            Select::make('icon')->label('آیکون')->options(['footsteps' => 'قدم', 'chain' => 'زنجیره', 'trail' => 'مسیر', 'route' => 'مسافت', 'calendar' => 'تقویم', 'flag' => 'پرچم', 'bolt' => 'انرژی', 'medal' => 'مدال'])->default('medal'),
            TextInput::make('sort')->label('ترتیب')->numeric()->default(0),
            Toggle::make('is_active')->label('فعال')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('نام')->description(fn (Achievement $r) => $r->description),
                TextColumn::make('metric')->label('معیار')->formatStateUsing(fn ($state) => AchievementService::METRICS[$state] ?? $state),
                TextColumn::make('threshold')->label('آستانه')->numeric(),
                TextColumn::make('xp_reward')->label('XP'),
                TextColumn::make('point_reward')->label('امتیاز'),
                TextColumn::make('unlocked')->label('کسب‌شده')->state(fn (Achievement $r) => UserAchievement::query()->where('achievement_id', $r->id)->count()),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAchievements::route('/'),
            'create' => Pages\CreateAchievement::route('/create'),
            'edit' => Pages\EditAchievement::route('/{record}/edit'),
        ];
    }
}
