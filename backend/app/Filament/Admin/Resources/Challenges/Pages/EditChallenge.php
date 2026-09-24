<?php

namespace App\Filament\Admin\Resources\Challenges\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\Challenges\ChallengeResource;
use Filament\Resources\Pages\EditRecord;

class EditChallenge extends EditRecord
{
    use AuditsRecordChanges;

    protected static string $resource = ChallengeResource::class;
}
