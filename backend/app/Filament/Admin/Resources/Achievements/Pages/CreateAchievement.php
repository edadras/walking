<?php

namespace App\Filament\Admin\Resources\Achievements\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\Achievements\AchievementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAchievement extends CreateRecord
{
    use AuditsRecordChanges;

    protected static string $resource = AchievementResource::class;
}
