<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::PendingApproval => 'در انتظار تأیید',
            self::Active => 'فعال',
            self::Paused => 'متوقف',
            self::Ended => 'پایان‌یافته',
            self::Rejected => 'ردشده',
        };
    }
}
