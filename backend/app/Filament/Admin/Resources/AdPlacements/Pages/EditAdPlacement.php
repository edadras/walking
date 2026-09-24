<?php

namespace App\Filament\Admin\Resources\AdPlacements\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\AdPlacements\AdPlacementResource;
use Filament\Resources\Pages\EditRecord;

class EditAdPlacement extends EditRecord
{
    use AuditsRecordChanges;

    protected static string $resource = AdPlacementResource::class;
}
