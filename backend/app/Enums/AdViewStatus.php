<?php

namespace App\Enums;

enum AdViewStatus: string
{
    case Started = 'started';
    case Rewarded = 'rewarded';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Started => 'در حال نمایش',
            self::Rewarded => 'پاداش داده شد',
            self::Rejected => 'ردشده',
            self::Expired => 'منقضی',
        };
    }
}
