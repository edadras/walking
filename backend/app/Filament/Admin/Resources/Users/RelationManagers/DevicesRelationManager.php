<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use App\Enums\DeviceStatus;
use App\Enums\IntegrityVerdict;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'devices';

    protected static ?string $title = 'دستگاه‌ها';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('model')->label('مدل')->formatStateUsing(fn ($record) => trim($record->manufacturer.' '.$record->model)),
                TextColumn::make('os_version')->label('Android'),
                TextColumn::make('app_version')->label('نسخه اپ'),
                TextColumn::make('integrity_verdict')->label('Integrity')->badge()->formatStateUsing(fn (?IntegrityVerdict $state) => $state?->label()),
                TextColumn::make('trust_score')->label('اعتماد'),
                IconColumn::make('root_suspected')->label('روت')->boolean(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (DeviceStatus $state) => $state->label()),
                TextColumn::make('pivot.last_seen_at')->label('آخرین استفاده')->since(),
            ]);
    }
}
