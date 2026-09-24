<?php

namespace App\Filament\Sponsor\Resources\Team\Pages;

use App\Filament\Sponsor\Resources\Team\TeamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTeam extends ListRecords
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return TeamResource::canCreate() ? [CreateAction::make()] : [];
    }
}
