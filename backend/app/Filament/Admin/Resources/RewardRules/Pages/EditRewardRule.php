<?php

namespace App\Filament\Admin\Resources\RewardRules\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\RewardRules\RewardRuleResource;
use Filament\Resources\Pages\EditRecord;

class EditRewardRule extends EditRecord
{
    use AuditsRecordChanges;

    protected static string $resource = RewardRuleResource::class;

    protected function afterSave(): void
    {
        RewardRuleResource::flush();
    }
}
