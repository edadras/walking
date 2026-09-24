<?php

namespace App\Enums;

enum DiscountType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case FreeItem = 'free_item';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'درصدی',
            self::Fixed => 'مبلغ ثابت (ریال)',
            self::FreeItem => 'کالای رایگان',
        };
    }
}
