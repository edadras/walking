<?php

namespace App\Filament\Admin\Resources\Achievements\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\Achievements\AchievementResource;
use Filament\Resources\Pages\EditRecord;

class EditAchievement extends EditRecord
{
    use AuditsRecordChanges;

    protected static string $resource = AchievementResource::class;
}
