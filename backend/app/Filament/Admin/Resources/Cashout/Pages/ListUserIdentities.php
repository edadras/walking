<?php

namespace App\Filament\Admin\Resources\Cashout\Pages;

use App\Filament\Admin\Resources\Cashout\UserIdentityResource;
use Filament\Resources\Pages\ListRecords;

class ListUserIdentities extends ListRecords
{
    protected static string $resource = UserIdentityResource::class;
}
