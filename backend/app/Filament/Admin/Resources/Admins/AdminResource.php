<?php

namespace App\Filament\Admin\Resources\Admins;

use App\Enums\AdminRole;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\Admin;
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
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AdminResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = Admin::class;

    protected static ?string $viewAbility = 'admins.manage';

    protected static ?string $manageAbility = 'admins.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'امنیت';

    protected static ?string $modelLabel = 'مدیر';

    protected static ?string $pluralModelLabel = 'مدیران';

    /** Admins are deactivated, never deleted (their audit trail must keep resolving). */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('نام')->required()->maxLength(100),
            TextInput::make('email')->label('ایمیل')->email()->required()->unique(ignoreRecord: true),
            Select::make('role')->label('نقش')->required()
                ->options(collect(AdminRole::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])),
            TextInput::make('password')->label('رمز عبور')->password()->revealable()
                ->rule('min:12')
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state)),
            Toggle::make('is_active')->label('فعال')->default(true)
                ->disabled(fn (?Admin $record) => $record?->is(auth('admin')->user())),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('email')->label('ایمیل')->searchable(),
                TextColumn::make('role')->label('نقش')->badge()->formatStateUsing(fn (AdminRole $state) => $state->label()),
                IconColumn::make('is_active')->label('فعال')->boolean(),
                TextColumn::make('last_login_at')->label('آخرین ورود')->since()->placeholder('—'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'edit' => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }
}
