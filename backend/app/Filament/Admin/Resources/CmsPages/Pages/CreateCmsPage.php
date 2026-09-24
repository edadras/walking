<?php

namespace App\Filament\Admin\Resources\CmsPages\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\CmsPages\CmsPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCmsPage extends CreateRecord
{
    use AuditsRecordChanges;

    protected static string $resource = CmsPageResource::class;
}
