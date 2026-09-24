<?php

namespace App\Filament\Admin\Resources\CmsPages\Pages;

use App\Filament\Admin\Resources\CmsPages\CmsPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCmsPages extends ListRecords
{
    protected static string $resource = CmsPageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
