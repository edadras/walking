<?php

namespace App\Filament\Sponsor\Resources\Coupons\Pages;

use App\Enums\CouponStatus;
use App\Filament\Sponsor\Resources\Coupons\CouponResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    protected static string $resource = CouponResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'sponsor_id' => CouponResource::sponsorId(), 'status' => CouponStatus::Draft];
    }
}
