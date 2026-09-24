<?php

namespace App\Filament\Sponsor\Resources\Coupons\Pages;

use App\Filament\Sponsor\Resources\Coupons\CouponResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCoupons extends ListRecords
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return CouponResource::canCreate() ? [CreateAction::make()] : [];
    }
}
