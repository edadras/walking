<?php

namespace App\Filament\Admin\Resources\Cashout\Pages;

use App\Domain\Cashout\CashoutService;
use App\Filament\Admin\Resources\Cashout\CashoutRequestResource;
use App\Models\CashoutRequest;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCashoutRequests extends ListRecords
{
    protected static string $resource = CashoutRequestResource::class;

    /** Remaining payout budget, so finance sees why an approval would be refused. */
    public function getSubheading(): ?string
    {
        $parts = [];
        foreach (app(CashoutService::class)->budget() as $period => $b) {
            if ($b['limit'] > 0) {
                $parts[] = ($period === 'daily' ? 'بودجه امروز' : 'بودجه این ماه').': '.number_format(max(0, $b['limit'] - $b['used'])).' از '.number_format($b['limit']).' ریال باقی‌مانده';
            }
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

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
