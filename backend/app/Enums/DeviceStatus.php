<?php

namespace App\Enums;

enum DeviceStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Blocked => 'مسدود',
            self::Revoked => 'لغوشده',
        };
    }
}
