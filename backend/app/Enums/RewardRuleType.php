<?php

namespace App\Enums;

enum RewardRuleType: string
{
    /** steps → points rate (e.g. 1000 steps = 10 points). The active one with the highest priority wins. */
    case StepRate = 'step_rate';
    /** Time-bound multiplier (weekday/bonus hours/campaign). */
    case Multiplier = 'multiplier';
    case DailyCap = 'daily_cap';
    case WeeklyCap = 'weekly_cap';
    case MaxRewardedSteps = 'max_rewarded_steps';
    case GoalBonus = 'goal_bonus';
    /** params: {"7": 20, "30": 100} → bonus points when the streak reaches N days. */
    case StreakBonus = 'streak_bonus';

    public function label(): string
    {
        return match ($this) {
            self::StepRate => 'نرخ قدم به امتیاز',
            self::Multiplier => 'ضریب',
            self::DailyCap => 'سقف روزانه امتیاز',
            self::WeeklyCap => 'سقف هفتگی امتیاز',
            self::MaxRewardedSteps => 'سقف قدم پاداش‌دار روزانه',
            self::GoalBonus => 'پاداش هدف روزانه',
            self::StreakBonus => 'پاداش روزهای متوالی',
        };
    }
}
