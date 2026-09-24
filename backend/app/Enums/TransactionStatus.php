<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در حال بررسی',
            self::Completed => 'انجام‌شده',
            self::Reversed => 'لغوشده',
        };
    }
}
