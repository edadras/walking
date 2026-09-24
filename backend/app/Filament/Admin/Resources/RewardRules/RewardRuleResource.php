<?php

namespace App\Filament\Admin\Resources\RewardRules;

use App\Domain\Reward\RewardRules;
use App\Enums\RewardRuleType;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\RewardRule;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class RewardRuleResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = RewardRule::class;

    protected static ?string $viewAbility = 'rewards.manage';

    protected static ?string $manageAbility = 'rewards.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'امتیاز و کیف پول';

    protected static ?string $modelLabel = 'قانون پاداش';

    protected static ?string $pluralModelLabel = 'قوانین پاداش';

    public static function form(Schema $schema): Schema
    {
        $is = fn (RewardRuleType ...$types) => fn (Get $get) => in_array($get('rule_type') instanceof RewardRuleType ? $get('rule_type') : RewardRuleType::tryFrom((string) $get('rule_type')), $types, true);

        return $schema->columns(2)->components([
            TextInput::make('name')->label('نام')->required()->maxLength(120),
            Select::make('rule_type')->label('نوع')->required()->live()
                ->options(collect(RewardRuleType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
            TextInput::make('steps')->label('قدم')->numeric()->minValue(1)->visible($is(RewardRuleType::StepRate))->required($is(RewardRuleType::StepRate)),
            TextInput::make('points')->label('امتیاز')->numeric()->minValue(0)->visible($is(RewardRuleType::StepRate, RewardRuleType::GoalBonus))->required($is(RewardRuleType::StepRate, RewardRuleType::GoalBonus)),
            TextInput::make('multiplier')->label('ضریب')->numeric()->minValue(1)->maxValue(5)->step(0.05)->visible($is(RewardRuleType::Multiplier))->required($is(RewardRuleType::Multiplier)),
            TextInput::make('cap')->label('سقف')->numeric()->minValue(0)->visible($is(RewardRuleType::DailyCap, RewardRuleType::WeeklyCap, RewardRuleType::MaxRewardedSteps))->required($is(RewardRuleType::DailyCap, RewardRuleType::WeeklyCap, RewardRuleType::MaxRewardedSteps)),
            CheckboxList::make('days_of_week')->label('روزهای هفته (خالی = همه)')->visible($is(RewardRuleType::Multiplier))->columns(7)
                ->options([6 => 'شنبه', 0 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه'])
                ->formatStateUsing(fn (?string $state) => $state ? explode(',', $state) : [])
                ->dehydrateStateUsing(fn (?array $state) => $state ? implode(',', $state) : null)
                ->columnSpanFull(),
            TimePicker::make('start_time')->label('از ساعت (Bonus hours)')->seconds(false)->visible($is(RewardRuleType::Multiplier)),
            TimePicker::make('end_time')->label('تا ساعت')->seconds(false)->visible($is(RewardRuleType::Multiplier)),
            KeyValue::make('params')->label('روز متوالی → امتیاز')->keyLabel('روز')->valueLabel('امتیاز')->visible($is(RewardRuleType::StreakBonus))->columnSpanFull(),
            DateTimePicker::make('starts_at')->label('شروع اعتبار'),
            DateTimePicker::make('ends_at')->label('پایان اعتبار')->after('starts_at'),
            TextInput::make('priority')->label('اولویت')->numeric()->default(100),
            Toggle::make('is_active')->label('فعال')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('rule_type')
            ->columns([
                TextColumn::make('name')->label('نام'),
                TextColumn::make('rule_type')->label('نوع')->formatStateUsing(fn (RewardRuleType $state) => $state->label())->badge(),
                TextColumn::make('value')->label('مقدار')->state(fn (RewardRule $r) => match ($r->rule_type) {
                    RewardRuleType::StepRate => "{$r->steps} قدم = {$r->points} امتیاز",
                    RewardRuleType::Multiplier => '×'.$r->multiplier,
                    RewardRuleType::GoalBonus => "{$r->points} امتیاز",
                    RewardRuleType::StreakBonus => json_encode($r->params, JSON_UNESCAPED_UNICODE),
                    default => (string) $r->cap,
                }),
                TextColumn::make('priority')->label('اولویت'),
                IconColumn::make('is_active')->label('فعال')->boolean(),
                TextColumn::make('ends_at')->label('پایان')->dateTime()->placeholder('—'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRewardRules::route('/'),
            'create' => Pages\CreateRewardRule::route('/create'),
            'edit' => Pages\EditRewardRule::route('/{record}/edit'),
        ];
    }

    public static function flush(): void
    {
        app(RewardRules::class)->flush();
    }
}
