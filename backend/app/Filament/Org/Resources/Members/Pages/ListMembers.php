<?php

namespace App\Filament\Org\Resources\Members\Pages;

use App\Filament\Org\Resources\Members\MemberResource;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;
}
