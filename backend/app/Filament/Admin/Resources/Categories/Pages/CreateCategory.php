<?php

namespace App\Filament\Admin\Resources\Categories\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    use AuditsRecordChanges;

    protected static string $resource = CategoryResource::class;
}
