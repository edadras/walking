<?php

namespace App\Enums;

enum VisitStatus: string
{
    case Started = 'started';
    case Verified = 'verified';
    case Rewarded = 'rewarded';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Started => 'در حال انجام',
            self::Verified => 'تأییدشده',
            self::Rewarded => 'پاداش داده شد',
            self::Rejected => 'ردشده',
            self::Expired => 'منقضی',
        };
    }
}
