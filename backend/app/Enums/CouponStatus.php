<?php

namespace App\Enums;

enum CouponStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Paused = 'paused';
    case Expired = 'expired';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::PendingApproval => 'در انتظار تأیید',
            self::Active => 'فعال',
            self::Paused => 'متوقف',
            self::Expired => 'منقضی',
            self::Rejected => 'ردشده',
        };
    }
}
