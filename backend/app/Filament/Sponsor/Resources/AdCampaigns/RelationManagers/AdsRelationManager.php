<?php

namespace App\Filament\Sponsor\Resources\AdCampaigns\RelationManagers;

use App\Enums\CampaignStatus;
use App\Filament\Shared\AdForms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AdsRelationManager extends RelationManager
{
    protected static string $relationship = 'ads';

    protected static ?string $title = 'آگهی‌ها';

    public function form(Schema $schema): Schema
    {
        return AdForms::creativesForm($schema);
    }

    public function table(Table $table): Table
    {
        // Creatives are frozen once submitted (the approved content is what runs).
        return AdForms::creativesTable($table, fn () => in_array($this->getOwnerRecord()->status, [CampaignStatus::Draft, CampaignStatus::Rejected], true));
    }
}
