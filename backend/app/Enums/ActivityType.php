<?php

namespace App\Enums;

enum ActivityType: string
{
    case Walking = 'walking';
    case Running = 'running';
    case Mixed = 'mixed';
    case Vehicle = 'vehicle';
    case Bicycle = 'bicycle';
    case Still = 'still';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Walking => 'پیاده‌روی',
            self::Running => 'دویدن',
            self::Mixed => 'ترکیبی',
            self::Vehicle => 'خودرو',
            self::Bicycle => 'دوچرخه',
            self::Still => 'ساکن',
            self::Unknown => 'نامشخص',
        };
    }
}
