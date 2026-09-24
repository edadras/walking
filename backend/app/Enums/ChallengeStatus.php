<?php

namespace App\Enums;

enum ChallengeStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Ended = 'ended';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::PendingApproval => 'در انتظار تأیید',
            self::Active => 'فعال',
            self::Ended => 'پایان‌یافته',
            self::Cancelled => 'لغوشده',
            self::Rejected => 'ردشده',
        };
    }
}
