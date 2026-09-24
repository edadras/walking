<?php

namespace App\Filament\Admin\Resources\Locations\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\Locations\LocationResource;
use App\Filament\Shared\MapsOpeningHours;
use Filament\Resources\Pages\EditRecord;

class EditLocation extends EditRecord
{
    use AuditsRecordChanges, MapsOpeningHours;

    protected static string $resource = LocationResource::class;
}
