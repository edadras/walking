<?php

namespace App\Filament\Org\Resources\Challenges\Pages;

use App\Filament\Org\Resources\Challenges\ChallengeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChallenge extends CreateRecord
{
    protected static string $resource = ChallengeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ChallengeResource::scoped($data);
    }
}
