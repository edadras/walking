<?php

namespace App\Filament\Admin\Resources\AdProviders;

use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\AdProvider;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Only super admins: enabling a network and its callback secret decide who can mint ad rewards. */
class AdProviderResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = AdProvider::class;

    protected static ?string $viewAbility = 'settings.manage';

    protected static ?string $manageAbility = 'settings.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = 'تبلیغات';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'شبکه تبلیغاتی';

    protected static ?string $pluralModelLabel = 'شبکه‌های تبلیغاتی';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('name')->label('نام')->required(),
            Toggle::make('is_enabled')->label('فعال')
                ->helperText('برای شبکه‌های خارجی فقط پس از بررسی مستندات رسمی و تست قرارداد Callback فعال کنید.'),
            TextInput::make('webhook_secret')->label('کلید امضای Callback (HMAC-SHA256)')->password()->revealable()->minLength(24)
                ->dehydrated(fn ($state) => filled($state))->placeholder('بدون تغییر'),
            Textarea::make('notes')->label('یادداشت')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('key')->label('کلید'),
            TextColumn::make('name')->label('نام'),
            IconColumn::make('is_enabled')->label('فعال')->boolean(),
            TextColumn::make('callback')->label('آدرس Callback')->state(fn (AdProvider $r) => $r->isInternal() ? '—' : url('/api/v1/webhooks/ads/'.$r->key))->copyable(),
            TextColumn::make('notes')->label('یادداشت')->limit(50),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdProviders::route('/'),
            'edit' => Pages\EditAdProvider::route('/{record}/edit'),
        ];
    }
}
