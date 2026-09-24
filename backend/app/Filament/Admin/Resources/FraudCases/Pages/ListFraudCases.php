<?php

namespace App\Filament\Admin\Resources\FraudCases\Pages;

use App\Filament\Admin\Resources\FraudCases\FraudCaseResource;
use Filament\Resources\Pages\ListRecords;

class ListFraudCases extends ListRecords
{
    protected static string $resource = FraudCaseResource::class;
}
