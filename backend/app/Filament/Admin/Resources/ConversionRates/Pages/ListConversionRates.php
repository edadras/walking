<?php

namespace App\Filament\Admin\Resources\ConversionRates\Pages;

use App\Domain\Audit\AuditLogger;
use App\Domain\Wallet\ConversionRate;
use App\Filament\Admin\Resources\ConversionRates\ConversionRateResource;
use App\Models\Admin;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListConversionRates extends ListRecords
{
    protected static string $resource = ConversionRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new_rate')
                ->label('ثبت نرخ جدید')
                ->visible(fn () => auth('admin')->user() instanceof Admin && auth('admin')->user()->hasAbility('rewards.manage'))
                ->requiresConfirmation()
                ->modalDescription('نرخ جدید فقط روی تراکنش‌های آینده و نمایش ارزش فعلی اثر دارد.')
                ->schema([TextInput::make('rial')->label('ریال به ازای هر امتیاز')->numeric()->integer()->minValue(1)->maxValue(1_000_000)->required()])
                ->action(function (array $data) {
                    $rates = app(ConversionRate::class);
                    $old = $rates->current();
                    $rate = $rates->set((int) $data['rial'], auth('admin')->user());
                    app(AuditLogger::class)->log('conversion_rate.changed', $rate, ['rial_per_point' => $old], ['rial_per_point' => $rate->rial_per_point]);
                    Notification::make()->title('نرخ تبدیل ثبت شد.')->success()->send();
                }),
        ];
    }
}
