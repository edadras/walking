<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Banned = 'banned';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Suspended => 'تعلیق‌شده',
            self::Banned => 'مسدود',
            self::Deleted => 'حذف‌شده',
        };
    }

    public function canUseApp(): bool
    {
        return $this === self::Active;
    }
}
