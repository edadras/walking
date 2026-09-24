<?php

namespace App\Filament\Admin\Resources\FraudRules\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\FraudRules\FraudRuleResource;
use Filament\Resources\Pages\EditRecord;

class EditFraudRule extends EditRecord
{
    use AuditsRecordChanges;

    protected static string $resource = FraudRuleResource::class;

    protected function afterSave(): void
    {
        FraudRuleResource::changed();
    }
}
