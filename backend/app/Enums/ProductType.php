<?php

namespace App\Enums;

enum ProductType: string
{
    /** Shipped to an address. */
    case Physical = 'physical';
    /** A code from the product's code pool, delivered instantly (gift cards, subscriptions). */
    case DigitalCode = 'digital_code';
    /** Issues a sponsor coupon (Phase 5) to the buyer. */
    case Coupon = 'coupon';
    /** Fulfilled manually by the team (e.g. a charity donation, a service). */
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Physical => 'کالای فیزیکی',
            self::DigitalCode => 'کد دیجیتال',
            self::Coupon => 'کوپن',
            self::Service => 'خدمت / اهدا',
        };
    }

    public function needsAddress(): bool
    {
        return $this === self::Physical;
    }

    public function deliveredInstantly(): bool
    {
        return in_array($this, [self::DigitalCode, self::Coupon], true);
    }
}
