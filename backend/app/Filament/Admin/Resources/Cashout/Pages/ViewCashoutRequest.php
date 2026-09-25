<?php

namespace App\Filament\Admin\Resources\Cashout\Pages;

use App\Filament\Admin\Resources\Cashout\CashoutRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCashoutRequest extends ViewRecord
{
    protected static string $resource = CashoutRequestResource::class;

    protected function getHeaderActions(): array
    {
        return CashoutRequestResource::workflowActions();
    }
}
