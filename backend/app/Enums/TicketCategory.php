<?php

namespace App\Enums;

enum TicketCategory: string
{
    case Account = 'account';
    case Points = 'points';
    case Steps = 'steps';
    case Store = 'store';
    case Sponsor = 'sponsor';
    case Technical = 'technical';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Account => 'حساب کاربری',
            self::Points => 'امتیاز و کیف پول',
            self::Steps => 'ثبت قدم',
            self::Store => 'فروشگاه و سفارش',
            self::Sponsor => 'مکان‌ها و کوپن‌ها',
            self::Technical => 'مشکل فنی',
            self::Other => 'سایر',
        };
    }
}
