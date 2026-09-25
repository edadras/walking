<?php

namespace App\Enums;

enum QuestMetric: string
{
    case Steps = 'steps';
    case ActiveMinutes = 'active_minutes';
    case DistanceM = 'distance_m';
    case GoalDays = 'goal_days';
    case ActiveWalks = 'active_walks';
    case WaterMl = 'water_ml';
    case SponsorVisits = 'sponsor_visits';

    public function label(): string
    {
        return match ($this) {
            self::Steps => 'قدم تأییدشده',
            self::ActiveMinutes => 'دقیقه فعالیت',
            self::DistanceM => 'مسافت (متر)',
            self::GoalDays => 'روز رسیدن به هدف',
            self::ActiveWalks => 'پیاده‌روی ثبت‌شده',
            self::WaterMl => 'آب نوشیده (میلی‌لیتر)',
            self::SponsorVisits => 'بازدید از شعبه اسپانسر',
        };
    }

    /** Short unit shown next to the progress numbers in the app. */
    public function unit(): string
    {
        return match ($this) {
            self::Steps => 'قدم',
            self::ActiveMinutes => 'دقیقه',
            self::DistanceM => 'متر',
            self::GoalDays => 'روز',
            self::ActiveWalks => 'پیاده‌روی',
            self::WaterMl => 'میلی‌لیتر',
            self::SponsorVisits => 'بازدید',
        };
    }
}
