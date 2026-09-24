<?php

namespace App\Filament\Sponsor\Resources\Team\Pages;

use App\Filament\Sponsor\Resources\Team\TeamResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMember extends CreateRecord
{
    protected static string $resource = TeamResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'sponsor_id' => TeamResource::sponsorId()];
    }
}
