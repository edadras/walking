<?php

namespace App\Filament\Admin\Resources\AdProviders\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\AdProviders\AdProviderResource;
use Filament\Resources\Pages\EditRecord;

class EditAdProvider extends EditRecord
{
    use AuditsRecordChanges;

    protected static string $resource = AdProviderResource::class;
}
