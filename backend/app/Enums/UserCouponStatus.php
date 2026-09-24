<?php

namespace App\Enums;

enum UserCouponStatus: string
{
    case Available = 'available';
    case Used = 'used';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'قابل استفاده',
            self::Used => 'استفاده‌شده',
            self::Expired => 'منقضی',
            self::Revoked => 'لغوشده',
        };
    }
}
