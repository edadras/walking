<?php

namespace App\Filament\Sponsor\Resources\Campaigns\Pages;

use App\Enums\CampaignStatus;
use App\Filament\Sponsor\Resources\Campaigns\CampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'sponsor_id' => CampaignResource::sponsorId(), 'status' => CampaignStatus::Draft];
    }
}
