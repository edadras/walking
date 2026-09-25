<?php

namespace App\Filament\Admin\Resources\Cashout\Pages;

use App\Filament\Admin\Resources\Cashout\CashoutRequestResource;
use App\Models\CashoutRequest;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCashoutRequests extends ListRecords
{
    protected static string $resource = CashoutRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('خروجی واریز گروهی (CSV)')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->disabled(fn () => CashoutRequest::query()->where('status', CashoutRequest::APPROVED)->doesntExist())
                ->requiresConfirmation()->modalDescription('فایل شامل شبای کامل درخواست‌های تأییدشده است و در گزارش ممیزی ثبت می‌شود.')
                ->action(fn () => CashoutRequestResource::exportApproved()),
        ];
    }
}
