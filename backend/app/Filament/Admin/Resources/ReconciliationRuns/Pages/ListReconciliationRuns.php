<?php

namespace App\Filament\Admin\Resources\ReconciliationRuns\Pages;

use App\Domain\Wallet\LedgerReconciler;
use App\Filament\Admin\Resources\ReconciliationRuns\ReconciliationRunResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListReconciliationRuns extends ListRecords
{
    protected static string $resource = ReconciliationRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run')->label('اجرای الان')->icon(Heroicon::OutlinedPlay)->action(function () {
                $run = app(LedgerReconciler::class)->run();
                Notification::make()->title($run->status === 'ok' ? 'بدون مغایرت' : $run->issue_count.' مغایرت پیدا شد')
                    ->{$run->status === 'ok' ? 'success' : 'danger'}()->send();
            }),
        ];
    }
}
