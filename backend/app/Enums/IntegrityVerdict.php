<?php

namespace App\Enums;

/**
 * Normalised Play Integrity device verdict. Only ever set by the server after
 * decoding the integrity token; never trusted from the client.
 */
enum IntegrityVerdict: string
{
    case Strong = 'strong';
    case Device = 'device';
    case Basic = 'basic';
    case None = 'none';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Strong => 'قوی',
            self::Device => 'دستگاه معتبر',
            self::Basic => 'پایه',
            self::None => 'نامعتبر',
            self::Unavailable => 'در دسترس نیست',
        };
    }
}
