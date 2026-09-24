<?php

namespace App\Filament\Sponsor\Resources\Team;

use App\Enums\SponsorRole;
use App\Filament\Sponsor\Concerns\SponsorScoped;
use App\Models\SponsorUser;
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

class TeamResource extends Resource
{
    use SponsorScoped;

    protected static ?string $model = SponsorUser::class;

    /** Only owners (`*`) manage staff. */
    protected static string $ability = 'team.manage';

    protected static ?string $slug = 'team';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'تیم';

    protected static ?string $modelLabel = 'عضو تیم';

    protected static ?string $pluralModelLabel = 'اعضای تیم';

    public static function canEdit(Model $record): bool
    {
        // Owners cannot demote or deactivate themselves (the sponsor would be locked out).
        return static::canView($record) && $record->getKey() !== static::sponsorUser()?->getKey();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('نام')->required()->maxLength(120),
            TextInput::make('email')->label('ایمیل')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('phone')->label('تلفن')->tel()->maxLength(20),
            Select::make('role')->label('نقش')->required()->default(SponsorRole::Cashier->value)
                ->options(collect(SponsorRole::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])),
            TextInput::make('password')->label('رمز عبور')->password()->revealable()->minLength(10)
                ->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state)),
            Toggle::make('is_active')->label('فعال')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام'),
                TextColumn::make('email')->label('ایمیل'),
                TextColumn::make('role')->label('نقش')->badge()->formatStateUsing(fn (SponsorRole $state) => $state->label()),
                IconColumn::make('is_active')->label('فعال')->boolean(),
                TextColumn::make('last_login_at')->label('آخرین ورود')->since(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeam::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
        ];
    }
}
