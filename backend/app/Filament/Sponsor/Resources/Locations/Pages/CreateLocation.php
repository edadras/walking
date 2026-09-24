<?php

namespace App\Filament\Sponsor\Resources\Locations\Pages;

use App\Enums\LocationStatus;
use App\Filament\Shared\MapsOpeningHours;
use App\Filament\Sponsor\Resources\Locations\LocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLocation extends CreateRecord
{
    use MapsOpeningHours {
        mutateFormDataBeforeCreate as mapHoursBeforeCreate;
    }

    protected static string $resource = LocationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$this->mapHoursBeforeCreate($data), 'sponsor_id' => LocationResource::sponsorId(), 'status' => LocationStatus::Pending];
    }
}
