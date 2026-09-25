<?php

namespace App\Filament\Org\Resources\Challenges\Pages;

use App\Filament\Org\Resources\Challenges\ChallengeResource;
use Filament\Resources\Pages\ListRecords;

class ListChallenges extends ListRecords
{
    protected static string $resource = ChallengeResource::class;
}
