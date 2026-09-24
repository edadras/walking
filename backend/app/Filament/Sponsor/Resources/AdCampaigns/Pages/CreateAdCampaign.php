<?php

namespace App\Filament\Sponsor\Resources\AdCampaigns\Pages;

use App\Enums\CampaignStatus;
use App\Filament\Sponsor\Resources\AdCampaigns\AdCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdCampaign extends CreateRecord
{
    protected static string $resource = AdCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'sponsor_id' => AdCampaignResource::sponsorId(), 'status' => CampaignStatus::Draft];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
