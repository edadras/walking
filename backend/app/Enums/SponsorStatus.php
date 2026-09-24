<?php

namespace App\Enums;

enum SponsorStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار تأیید',
            self::Approved => 'تأییدشده',
            self::Rejected => 'ردشده',
            self::Suspended => 'تعلیق‌شده',
        };
    }
}
