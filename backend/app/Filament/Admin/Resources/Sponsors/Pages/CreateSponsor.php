<?php

namespace App\Filament\Admin\Resources\Sponsors\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\Sponsors\SponsorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSponsor extends CreateRecord
{
    use AuditsRecordChanges;

    protected static string $resource = SponsorResource::class;
}
