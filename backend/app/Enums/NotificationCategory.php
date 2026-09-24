<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case DailyGoal = 'daily_goal';
    case WaterReminder = 'water_reminder';
    case StreakWarning = 'streak_warning';
    case RewardReceived = 'reward_received';
    case Challenge = 'challenge';
    case SponsorCampaign = 'sponsor_campaign';
    case CouponExpiration = 'coupon_expiration';
    case OrderUpdate = 'order_update';
    case Announcement = 'announcement';

    public function label(): string
    {
        return match ($this) {
            self::DailyGoal => 'هدف روزانه',
            self::WaterReminder => 'یادآوری آب',
            self::StreakWarning => 'هشدار زنجیره روزها',
            self::RewardReceived => 'دریافت امتیاز',
            self::Challenge => 'چالش‌ها',
            self::SponsorCampaign => 'کمپین‌های اسپانسر',
            self::CouponExpiration => 'انقضای کوپن',
            self::OrderUpdate => 'وضعیت سفارش',
            self::Announcement => 'اطلاعیه‌ها',
        };
    }

    /** Transactional categories the user cannot switch off (they concern their own orders/money). */
    public function isMandatory(): bool
    {
        return $this === self::OrderUpdate;
    }
}
