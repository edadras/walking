<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use AuditsRecordChanges;

    protected static string $resource = ProductResource::class;
}
