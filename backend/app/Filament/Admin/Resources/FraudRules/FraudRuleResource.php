<?php

namespace App\Filament\Admin\Resources\FraudRules;

use App\Domain\Fraud\RuleRegistry;
use App\Domain\Settings\Settings;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\FraudRule;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FraudRuleResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = FraudRule::class;

    protected static ?string $viewAbility = 'fraud.manage';

    protected static ?string $manageAbility = 'fraud.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'ضد تقلب';

    protected static ?string $modelLabel = 'قانون';

    protected static ?string $pluralModelLabel = 'قوانین تشخیص تقلب';

    public static function canCreate(): bool
    {
        return false; // rules are code; admins tune them
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('نام')->disabled(),
            TextInput::make('key')->label('کلید')->disabled(),
            Toggle::make('is_enabled')->label('فعال'),
            TextInput::make('weight')->label('وزن (٪)')->numeric()->minValue(0)->maxValue(300)->required()
                ->helperText('۱۰۰ = وزن پیش‌فرض. ریسک نهایی = Σ ریسک × وزن / ۱۰۰'),
            KeyValue::make('params')->label('پارامترها')->keyLabel('پارامتر')->valueLabel('مقدار')->addable(false)->deletable(false)->editableKeys(false)
                ->dehydrateStateUsing(fn (?array $state) => collect($state ?? [])->map(fn ($v) => is_numeric($v) ? $v + 0 : $v)->all())
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('category')
            ->columns([
                TextColumn::make('name')->label('قانون')->description(fn (FraudRule $r) => $r->key),
                TextColumn::make('category')->label('دسته')->badge(),
                TextColumn::make('weight')->label('وزن')->suffix('٪'),
                ToggleColumn::make('is_enabled')->label('فعال')->afterStateUpdated(fn () => static::changed()),
                TextColumn::make('updated_at')->label('آخرین تغییر')->since(),
            ])
            ->recordActions([EditAction::make()]);
    }

    /** Invalidate the cached rule set and bump the version stamped on scored sessions. */
    public static function changed(): void
    {
        app(RuleRegistry::class)->flush();
        $settings = app(Settings::class);
        $settings->set('fraud.rule_set_version', $settings->int('fraud.rule_set_version') + 1, auth('admin')->id());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFraudRules::route('/'),
            'edit' => Pages\EditFraudRule::route('/{record}/edit'),
        ];
    }
}
