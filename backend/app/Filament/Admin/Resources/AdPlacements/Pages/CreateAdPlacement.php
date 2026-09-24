<?php

namespace App\Filament\Admin\Resources\AdPlacements\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\AdPlacements\AdPlacementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdPlacement extends CreateRecord
{
    use AuditsRecordChanges;

    protected static string $resource = AdPlacementResource::class;
}
