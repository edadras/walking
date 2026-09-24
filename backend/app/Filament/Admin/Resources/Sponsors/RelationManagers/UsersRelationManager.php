<?php

namespace App\Filament\Admin\Resources\Sponsors\RelationManagers;

use App\Enums\SponsorRole;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'کاربران پنل اسپانسر';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('نام')->required(),
            TextInput::make('email')->label('ایمیل')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('phone')->label('تلفن')->tel(),
            Select::make('role')->label('نقش')->required()->options(collect(SponsorRole::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])),
            TextInput::make('password')->label('رمز عبور')->password()->revealable()->minLength(10)
                ->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state)),
            Toggle::make('is_active')->label('فعال')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام'),
                TextColumn::make('email')->label('ایمیل'),
                TextColumn::make('role')->label('نقش')->badge()->formatStateUsing(fn (SponsorRole $state) => $state->label()),
                IconColumn::make('is_active')->label('فعال')->boolean(),
                TextColumn::make('last_login_at')->label('آخرین ورود')->since(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()]);
    }
}
