<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DailyActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'dailyActivities';

    protected static ?string $title = 'فعالیت روزانه';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('local_date', 'desc')
            ->columns([
                TextColumn::make('local_date')->label('روز')->date('Y-m-d'),
                TextColumn::make('raw_steps')->label('قدم ادعایی')->numeric(),
                TextColumn::make('verified_steps')->label('قدم تأییدشده')->numeric(),
                TextColumn::make('goal_steps')->label('هدف')->numeric(),
                IconColumn::make('goal_reached_at')->label('هدف کامل')->boolean(),
                TextColumn::make('distance_m')->label('مسافت (m)')->numeric(),
                TextColumn::make('sessions_count')->label('جلسه‌ها'),
            ]);
    }
}
