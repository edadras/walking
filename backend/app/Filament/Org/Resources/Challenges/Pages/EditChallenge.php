<?php

namespace App\Filament\Org\Resources\Challenges\Pages;

use App\Filament\Org\Resources\Challenges\ChallengeResource;
use Filament\Resources\Pages\EditRecord;

class EditChallenge extends EditRecord
{
    protected static string $resource = ChallengeResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ChallengeResource::scoped($data);
    }
}
