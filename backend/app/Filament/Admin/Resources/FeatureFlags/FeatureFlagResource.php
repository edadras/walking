<?php

namespace App\Filament\Admin\Resources\FeatureFlags;

use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\FeatureFlags;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\FeatureFlag;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class FeatureFlagResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = FeatureFlag::class;

    // Flags change product behaviour for everyone: super admin only.
    protected static ?string $viewAbility = 'flags.manage';

    protected static ?string $manageAbility = 'flags.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'تنظیمات';

    protected static ?string $modelLabel = 'قابلیت';

    protected static ?string $pluralModelLabel = 'قابلیت‌ها (Feature Flags)';

    public static function canCreate(): bool
    {
        return false; // Flags are defined in code (config/walk.php) and synced by the seeder.
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->label('کلید')->disabled(),
            TextInput::make('description')->label('توضیح')->maxLength(255),
            Toggle::make('is_enabled')->label('فعال'),
            TextInput::make('rollout_percent')->label('درصد انتشار')->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
            TextInput::make('min_app_version')->label('حداقل نسخه اپ')->placeholder('1.2.0')->regex('/^\d+\.\d+\.\d+$/'),
            CheckboxList::make('platforms')->label('سیستم‌عامل‌ها (خالی = همه)')
                ->options(['android' => 'Android', 'ios' => 'iOS'])
                ->formatStateUsing(fn (?string $state) => $state ? explode(',', $state) : [])
                ->dehydrateStateUsing(fn (?array $state) => $state ? implode(',', $state) : null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->label('کلید')->fontFamily('mono'),
                TextColumn::make('description')->label('توضیح'),
                ToggleColumn::make('is_enabled')->label('فعال')
                    ->afterStateUpdated(function (FeatureFlag $record, bool $state) {
                        app(FeatureFlags::class)->flush();
                        app(AuditLogger::class)->log('feature_flag.toggled', $record, ['is_enabled' => ! $state], ['is_enabled' => $state]);
                    }),
                TextColumn::make('rollout_percent')->label('انتشار')->suffix('%'),
                TextColumn::make('min_app_version')->label('حداقل نسخه')->placeholder('—'),
                TextColumn::make('updated_at')->label('به‌روزرسانی')->since(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeatureFlags::route('/'),
            'edit' => Pages\EditFeatureFlag::route('/{record}/edit'),
        ];
    }
}
