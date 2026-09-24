<?php

namespace App\Enums;

enum FraudCaseStatus: string
{
    case Open = 'open';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Flagged = 'flagged';
    case Banned = 'banned';
    case Safe = 'safe';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'باز',
            self::Approved => 'تأیید',
            self::Rejected => 'رد',
            self::Flagged => 'علامت‌گذاری',
            self::Banned => 'مسدود',
            self::Safe => 'امن',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::Flagged], true);
    }
}
