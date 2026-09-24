<?php

namespace App\Enums;

enum AdFormat: string
{
    case Banner = 'banner';
    case Native = 'native';
    case Rewarded = 'rewarded';

    public function label(): string
    {
        return match ($this) {
            self::Banner => 'بنر',
            self::Native => 'بومی (کارت)',
            self::Rewarded => 'جایزه‌دار',
        };
    }
}
