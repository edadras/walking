<?php

namespace App\Filament\Sponsor\Resources\Locations\Pages;

use App\Enums\LocationStatus;
use App\Filament\Shared\MapsOpeningHours;
use App\Filament\Sponsor\Resources\Locations\LocationResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditLocation extends EditRecord
{
    use MapsOpeningHours;

    protected static string $resource = LocationResource::class;

    /** Moving the pin or widening the fence needs a new review. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->fill($data);
        if ($record->isDirty(['latitude', 'longitude', 'radius_m']) || $record->status === LocationStatus::Rejected) {
            $record->status = LocationStatus::Pending;
        }
        $record->save();

        return $record;
    }
}
