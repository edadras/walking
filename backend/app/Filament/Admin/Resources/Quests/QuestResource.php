<?php

namespace App\Filament\Admin\Resources\Quests;

use App\Enums\QuestMetric;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\Quest;
use App\Models\QuestClaim;
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

class QuestResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = Quest::class;

    protected static ?string $viewAbility = 'challenges.manage';

    protected static ?string $manageAbility = 'challenges.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'تعامل';

    protected static ?string $modelLabel = 'مأموریت';

    protected static ?string $pluralModelLabel = 'مأموریت‌ها';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('key')->label('کلید')->required()->alphaDash()->maxLength(48)->unique(ignoreRecord: true),
            TextInput::make('title')->label('عنوان')->required()->maxLength(80),
            TextInput::make('description')->label('توضیح')->maxLength(200)->columnSpanFull(),
            Select::make('period')->label('دوره')->required()->options(['daily' => 'روزانه', 'weekly' => 'هفتگی (شنبه تا جمعه)']),
            Select::make('metric')->label('معیار')->required()->options(collect(QuestMetric::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()])),
            TextInput::make('target')->label('هدف')->numeric()->integer()->minValue(1)->required(),
            TextInput::make('reward_points')->label('امتیاز')->numeric()->integer()->minValue(0)->maxValue(1000)->default(0),
            TextInput::make('reward_xp')->label('XP')->numeric()->integer()->minValue(0)->default(0),
            TextInput::make('sort')->label('ترتیب')->numeric()->default(0),
            Toggle::make('is_active')->label('فعال')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('title')->label('عنوان')->description(fn (Quest $r) => $r->description),
                TextColumn::make('period')->label('دوره')->badge()->formatStateUsing(fn (string $state) => $state === 'daily' ? 'روزانه' : 'هفتگی'),
                TextColumn::make('metric')->label('معیار')->formatStateUsing(fn (QuestMetric $state) => $state->label()),
                TextColumn::make('target')->label('هدف')->numeric(),
                TextColumn::make('reward_points')->label('امتیاز'),
                TextColumn::make('claims')->label('دریافت ۷ روز اخیر')
                    ->state(fn (Quest $r) => QuestClaim::query()->where('quest_id', $r->id)->where('claimed_at', '>=', now()->subDays(7))->count()),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuests::route('/'),
            'create' => Pages\CreateQuest::route('/create'),
            'edit' => Pages\EditQuest::route('/{record}/edit'),
        ];
    }
}
