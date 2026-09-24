<?php

namespace App\Filament\Admin\Resources\AdProviders\Pages;

use App\Filament\Admin\Resources\AdProviders\AdProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdProviders extends ListRecords
{
    protected static string $resource = AdProviderResource::class;

    protected function getHeaderActions(): array
    {
        return AdProviderResource::canCreate() ? [CreateAction::make()] : [];
    }
}
