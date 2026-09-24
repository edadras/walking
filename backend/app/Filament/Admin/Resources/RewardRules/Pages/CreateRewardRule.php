<?php

namespace App\Filament\Admin\Resources\RewardRules\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\RewardRules\RewardRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRewardRule extends CreateRecord
{
    use AuditsRecordChanges;

    protected static string $resource = RewardRuleResource::class;

    protected function afterCreate(): void
    {
        $this->auditCreate();
        RewardRuleResource::flush();
    }
}
