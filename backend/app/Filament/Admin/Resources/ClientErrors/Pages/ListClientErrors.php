<?php

namespace App\Filament\Admin\Resources\ClientErrors\Pages;

use App\Filament\Admin\Resources\ClientErrors\ClientErrorResource;
use Filament\Resources\Pages\ListRecords;

class ListClientErrors extends ListRecords
{
    protected static string $resource = ClientErrorResource::class;
}
