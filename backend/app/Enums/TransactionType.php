<?php

namespace App\Enums;

enum TransactionType: string
{
    case WalkingReward = 'walking_reward';
    case GoalBonus = 'goal_bonus';
    case StreakBonus = 'streak_bonus';
    case ChallengeReward = 'challenge_reward';
    case QuestReward = 'quest_reward';
    case SponsorReward = 'sponsor_reward';
    case ReferralReward = 'referral_reward';
    case AdReward = 'ad_reward';
    case AchievementReward = 'achievement_reward';
    case CouponReward = 'coupon_reward';
    case Purchase = 'purchase';
    case StreakFreeze = 'streak_freeze';
    case Cashout = 'cashout';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case Expiration = 'expiration';

    public function label(): string
    {
        return match ($this) {
            self::WalkingReward => 'پاداش قدم',
            self::GoalBonus => 'پاداش هدف روزانه',
            self::StreakBonus => 'پاداش روزهای متوالی',
            self::ChallengeReward => 'پاداش چالش',
            self::QuestReward => 'پاداش مأموریت',
            self::SponsorReward => 'پاداش اسپانسر',
            self::ReferralReward => 'پاداش دعوت',
            self::AdReward => 'پاداش تبلیغ',
            self::AchievementReward => 'پاداش دستاورد',
            self::CouponReward => 'پاداش کوپن',
            self::Purchase => 'خرید',
            self::StreakFreeze => 'خرید محافظ زنجیره',
            self::Cashout => 'برداشت نقدی',
            self::Refund => 'بازگشت وجه',
            self::Adjustment => 'اصلاح',
            self::Expiration => 'انقضا',
        };
    }

    /** Wallet history filter groups (spec §14). */
    public function group(): string
    {
        return match ($this) {
            self::Purchase, self::StreakFreeze => 'purchase',
            self::Cashout => 'cashout',
            self::Adjustment, self::Expiration => 'adjustment',
            self::SponsorReward, self::CouponReward => 'sponsor',
            self::Refund => 'earned',
            default => 'reward',
        };
    }

    public function isCredit(): bool
    {
        return ! in_array($this, [self::Purchase, self::Expiration, self::Cashout, self::StreakFreeze], true);
    }
}
