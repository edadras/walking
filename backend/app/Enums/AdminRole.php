<?php

namespace App\Enums;

enum AdminRole: string
{
    case SuperAdmin = 'super_admin';
    case Operations = 'operations';
    case FraudAnalyst = 'fraud_analyst';
    case Finance = 'finance';
    case StoreManager = 'store_manager';
    case SponsorManager = 'sponsor_manager';
    case Support = 'support';
    case ContentEditor = 'content_editor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'مدیر ارشد',
            self::Operations => 'عملیات',
            self::FraudAnalyst => 'تحلیلگر تقلب',
            self::Finance => 'مالی',
            self::StoreManager => 'مدیر فروشگاه',
            self::SponsorManager => 'مدیر اسپانسرها',
            self::Support => 'پشتیبانی',
            self::ContentEditor => 'ویراستار محتوا',
        };
    }

    /** @return list<string> */
    public function abilities(): array
    {
        return match ($this) {
            self::SuperAdmin => ['*'],
            self::Operations => ['users.view', 'users.moderate', 'devices.view', 'content.manage', 'challenges.manage', 'ops.view'],
            self::FraudAnalyst => ['users.view', 'users.moderate', 'devices.view', 'devices.moderate', 'fraud.manage', 'wallet.view', 'audit.view'],
            self::Finance => ['users.view', 'wallet.view', 'wallet.adjust', 'rewards.manage', 'reports.view', 'audit.view'],
            self::StoreManager => ['store.manage', 'orders.manage', 'users.view'],
            self::SponsorManager => ['sponsors.manage', 'ads.manage'],
            self::Support => ['users.view', 'devices.view', 'support.manage'],
            self::ContentEditor => ['content.manage'],
        };
    }

    public function can(string $ability): bool
    {
        $abilities = $this->abilities();

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }
}
