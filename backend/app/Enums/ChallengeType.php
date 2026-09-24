<?php

namespace App\Enums;

enum ChallengeType: string
{
    /** Reach target verified steps within the challenge window. */
    case Steps = 'steps';
    /** Reach target distance (metres) within the window. */
    case Distance = 'distance';
    /** Reach target steps in a single day during the window. */
    case Daily = 'daily';
    /** Steps challenge over a 7-day window. */
    case Weekly = 'weekly';
    /** Visit sponsor locations (progress = verified visits). */
    case Location = 'location';
    /** Steps challenge funded by a sponsor. */
    case Sponsored = 'sponsored';

    public function label(): string
    {
        return match ($this) {
            self::Steps => 'چالش قدم',
            self::Distance => 'چالش مسافت',
            self::Daily => 'چالش روزانه',
            self::Weekly => 'چالش هفتگی',
            self::Location => 'چالش مکانی',
            self::Sponsored => 'چالش اسپانسری',
        };
    }
}
