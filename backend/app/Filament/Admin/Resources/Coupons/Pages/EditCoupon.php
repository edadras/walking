<?php

namespace App\Filament\Admin\Resources\Coupons\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\Coupons\CouponResource;
use Filament\Resources\Pages\EditRecord;

class EditCoupon extends EditRecord
{
    use AuditsRecordChanges;

    protected static string $resource = CouponResource::class;
}
