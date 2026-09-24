<?php

namespace App\Filament\Admin\Resources\AdPlacements\Pages;

use App\Filament\Admin\Resources\AdPlacements\AdPlacementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdPlacements extends ListRecords
{
    protected static string $resource = AdPlacementResource::class;

    protected function getHeaderActions(): array
    {
        return AdPlacementResource::canCreate() ? [CreateAction::make()] : [];
    }
}
