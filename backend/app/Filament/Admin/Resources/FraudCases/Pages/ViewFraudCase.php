<?php

namespace App\Filament\Admin\Resources\FraudCases\Pages;

use App\Filament\Admin\Resources\FraudCases\FraudCaseResource;
use Filament\Resources\Pages\ViewRecord;

class ViewFraudCase extends ViewRecord
{
    protected static string $resource = FraudCaseResource::class;

    protected function getHeaderActions(): array
    {
        return FraudCaseResource::decisionActions();
    }
}
