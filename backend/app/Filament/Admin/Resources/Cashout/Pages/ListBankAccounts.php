<?php

namespace App\Filament\Admin\Resources\Cashout\Pages;

use App\Filament\Admin\Resources\Cashout\BankAccountResource;
use Filament\Resources\Pages\ListRecords;

class ListBankAccounts extends ListRecords
{
    protected static string $resource = BankAccountResource::class;
}
