<?php

namespace App\Filament\Admin\Resources\CmsPages\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\CmsPages\CmsPageResource;
use Filament\Resources\Pages\EditRecord;

class EditCmsPage extends EditRecord
{
    use AuditsRecordChanges;

    protected static string $resource = CmsPageResource::class;
}
