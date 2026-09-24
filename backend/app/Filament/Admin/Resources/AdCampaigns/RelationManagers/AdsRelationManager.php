<?php

namespace App\Filament\Admin\Resources\AdCampaigns\RelationManagers;

use App\Filament\Shared\AdForms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AdsRelationManager extends RelationManager
{
    protected static string $relationship = 'ads';

    protected static ?string $title = 'آگهی‌ها (Creative)';

    public function form(Schema $schema): Schema
    {
        return AdForms::creativesForm($schema);
    }

    public function table(Table $table): Table
    {
        return AdForms::creativesTable($table);
    }
}
