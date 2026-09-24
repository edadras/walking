<?php

namespace App\Filament\Admin\Resources\RewardRules\Pages;

use App\Filament\Admin\Resources\RewardRules\RewardRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRewardRules extends ListRecords
{
    protected static string $resource = RewardRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
