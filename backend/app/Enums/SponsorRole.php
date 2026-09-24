<?php

namespace App\Enums;

enum SponsorRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Analyst = 'analyst';
    case Cashier = 'cashier';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'مالک',
            self::Manager => 'مدیر',
            self::Analyst => 'تحلیلگر',
            self::Cashier => 'صندوق‌دار',
        };
    }

    /** @return list<string> */
    public function abilities(): array
    {
        return match ($this) {
            self::Owner => ['*'],
            self::Manager => ['locations.manage', 'campaigns.manage', 'coupons.manage', 'visits.view', 'analytics.view', 'qr.display', 'coupons.redeem'],
            self::Analyst => ['visits.view', 'analytics.view'],
            self::Cashier => ['qr.display', 'coupons.redeem'],
        };
    }

    public function can(string $ability): bool
    {
        $abilities = $this->abilities();

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }
}
