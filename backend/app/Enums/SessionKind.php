<?php

namespace App\Enums;

enum SessionKind: string
{
    /** Built from background step-counter reads (coarse windows). */
    case Passive = 'passive';

    /** A walk the user started explicitly (minute buckets, optional GPS). */
    case Active = 'active';

    public function label(): string
    {
        return $this === self::Passive ? 'پس‌زمینه' : 'پیاده‌روی';
    }
}
