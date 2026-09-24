<?php

namespace App\Filament\Admin\Resources\AdViews\Pages;

use App\Filament\Admin\Resources\AdViews\AdViewResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdViews extends ListRecords
{
    protected static string $resource = AdViewResource::class;

    protected function getHeaderActions(): array
    {
        return AdViewResource::canCreate() ? [CreateAction::make()] : [];
    }
}
