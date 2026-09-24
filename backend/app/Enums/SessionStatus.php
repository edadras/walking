<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Submitted = 'submitted';
    case Verified = 'verified';
    case PartiallyVerified = 'partially_verified';
    case UnderReview = 'under_review';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'در حال بررسی',
            self::Verified => 'تأییدشده',
            self::PartiallyVerified => 'تأیید بخشی',
            self::UnderReview => 'بررسی دستی',
            self::Rejected => 'ردشده',
        };
    }

    public function isScored(): bool
    {
        return in_array($this, [self::Verified, self::PartiallyVerified, self::Rejected], true);
    }
}
