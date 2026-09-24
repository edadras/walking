<?php

namespace App\Filament\Admin\Resources\WalkingSessions\Pages;

use App\Filament\Admin\Resources\WalkingSessions\WalkingSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListWalkingSessions extends ListRecords
{
    protected static string $resource = WalkingSessionResource::class;
}
