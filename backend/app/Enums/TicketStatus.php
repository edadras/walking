<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case AwaitingSupport = 'awaiting_support';
    case AwaitingUser = 'awaiting_user';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'باز',
            self::AwaitingSupport => 'در انتظار پشتیبانی',
            self::AwaitingUser => 'در انتظار پاسخ شما',
            self::Resolved => 'حل‌شده',
            self::Closed => 'بسته‌شده',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Resolved, self::Closed], true);
    }
}
