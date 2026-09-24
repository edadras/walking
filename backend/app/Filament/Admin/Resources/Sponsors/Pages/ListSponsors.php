<?php

namespace App\Filament\Admin\Resources\Sponsors\Pages;

use App\Filament\Admin\Resources\Sponsors\SponsorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSponsors extends ListRecords
{
    protected static string $resource = SponsorResource::class;

    protected function getHeaderActions(): array
    {
        return SponsorResource::canCreate() ? [CreateAction::make()] : [];
    }
}
