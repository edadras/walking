<?php

namespace App\Enums;

enum LocationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار تأیید',
            self::Approved => 'تأییدشده',
            self::Rejected => 'ردشده',
            self::Inactive => 'غیرفعال',
        };
    }
}
