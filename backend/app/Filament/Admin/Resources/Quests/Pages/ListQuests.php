<?php

namespace App\Filament\Admin\Resources\Quests\Pages;

use App\Filament\Admin\Resources\Quests\QuestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuests extends ListRecords
{
    protected static string $resource = QuestResource::class;

    protected function getHeaderActions(): array
    {
        return QuestResource::canCreate() ? [CreateAction::make()] : [];
    }
}
