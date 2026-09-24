<?php

/*
|--------------------------------------------------------------------------
| Business defaults
|--------------------------------------------------------------------------
| Safe fallbacks only. Every value here can be overridden at runtime from the
| admin panel through the `settings` table (see App\Domain\Settings\Settings).
| Keys are dotted and identical to the `settings.key` column.
*/

return [
    'panel_timezone' => env('PANEL_TIMEZONE', 'Asia/Tehran'),

    // Used only until an admin records the first conversion rate.
    'default_rial_per_point' => 500,

    'settings' => [
        // Auth
        'auth.otp_length' => ['value' => 5, 'group' => 'auth', 'public' => true, 'description' => 'تعداد ارقام کد یکبارمصرف'],
        'auth.otp_ttl_seconds' => ['value' => 120, 'group' => 'auth', 'public' => false, 'description' => 'مدت اعتبار کد (ثانیه)'],
        'auth.otp_max_attempts' => ['value' => 5, 'group' => 'auth', 'public' => false, 'description' => 'حداکثر تلاش برای هر کد'],
        'auth.otp_resend_seconds' => ['value' => 60, 'group' => 'auth', 'public' => true, 'description' => 'فاصله ارسال مجدد کد'],
        'auth.token_ttl_days' => ['value' => 90, 'group' => 'auth', 'public' => false, 'description' => 'اعتبار توکن ورود (روز)'],

        // Security
        'security.signature_max_skew_seconds' => ['value' => 300, 'group' => 'security', 'public' => false, 'description' => 'حداکثر اختلاف ساعت درخواست امضاشده'],
        'security.nonce_ttl_seconds' => ['value' => 600, 'group' => 'security', 'public' => false, 'description' => 'مدت نگهداری Nonce'],
        'security.require_integrity' => ['value' => false, 'group' => 'security', 'public' => false, 'description' => 'رد ثبت دستگاه بدون Play Integrity'],
        'security.timezone_change_cooldown_days' => ['value' => 7, 'group' => 'security', 'public' => false, 'description' => 'حداقل فاصله تغییر منطقه زمانی'],

        // App
        'app.min_supported_version' => ['value' => '1.0.0', 'group' => 'app', 'public' => true, 'description' => 'حداقل نسخه قابل استفاده'],
        'app.latest_version' => ['value' => '1.0.0', 'group' => 'app', 'public' => true, 'description' => 'آخرین نسخه منتشرشده'],
        'app.support_phone' => ['value' => null, 'group' => 'app', 'public' => true, 'description' => 'شماره پشتیبانی'],

        // Activity
        'activity.daily_goal_options' => ['value' => [5000, 7500, 10000, 12500, 15000], 'group' => 'activity', 'public' => true, 'description' => 'گزینه‌های هدف روزانه'],
        'activity.default_daily_goal' => ['value' => 7500, 'group' => 'activity', 'public' => true, 'description' => 'هدف روزانه پیش‌فرض'],
        'activity.min_daily_goal' => ['value' => 1000, 'group' => 'activity', 'public' => true, 'description' => 'حداقل هدف سفارشی'],
        'activity.max_daily_goal' => ['value' => 50000, 'group' => 'activity', 'public' => true, 'description' => 'حداکثر هدف سفارشی'],
        'activity.offline_max_age_days' => ['value' => 7, 'group' => 'activity', 'public' => true, 'description' => 'حداکثر عمر جلسه آفلاین قابل ارسال (روز)'],
        'activity.max_active_session_hours' => ['value' => 6, 'group' => 'activity', 'public' => true, 'description' => 'حداکثر طول پیاده‌روی فعال (ساعت)'],
        'activity.max_passive_session_minutes' => ['value' => 120, 'group' => 'activity', 'public' => true, 'description' => 'حداکثر طول جلسه پس‌زمینه (دقیقه)'],
        'activity.samples_retention_days' => ['value' => 30, 'group' => 'activity', 'public' => false, 'description' => 'نگهداری خلاصه‌های دقیقه‌ای (روز)'],
        'activity.default_height_cm' => ['value' => 170, 'group' => 'activity', 'public' => false, 'description' => 'قد پیش‌فرض برای تخمین طول گام'],
        'activity.default_weight_kg' => ['value' => 70, 'group' => 'activity', 'public' => false, 'description' => 'وزن پیش‌فرض برای تخمین کالری'],
        'analytics.retention_days' => ['value' => 180, 'group' => 'activity', 'public' => false, 'description' => 'نگهداری رویدادهای تحلیلی (روز)'],

        // Health
        'health.default_water_goal_ml' => ['value' => 2000, 'group' => 'health', 'public' => true, 'description' => 'هدف آب پیش‌فرض (میلی‌لیتر)'],
        'health.glass_ml' => ['value' => 250, 'group' => 'health', 'public' => true, 'description' => 'حجم هر لیوان'],

        // Fraud
        'fraud.review_threshold' => ['value' => 50, 'group' => 'fraud', 'public' => false, 'description' => 'آستانه بررسی دستی (ریسک)'],
        'fraud.reject_threshold' => ['value' => 80, 'group' => 'fraud', 'public' => false, 'description' => 'آستانه رد خودکار (ریسک)'],
        'fraud.rule_set_version' => ['value' => 1, 'group' => 'fraud', 'public' => false, 'description' => 'نسخه قوانین (خودکار با هر تغییر)'],

        // Reward
        'reward.hold_hours' => ['value' => 24, 'group' => 'reward', 'public' => true, 'description' => 'مدت بررسی امتیاز قبل از قابل استفاده شدن (ساعت)'],
        'reward.max_multiplier' => ['value' => 3, 'group' => 'reward', 'public' => false, 'description' => 'حداکثر ضریب ترکیبی'],

        // Gamification
        'gamification.steps_per_xp' => ['value' => 100, 'group' => 'gamification', 'public' => false, 'description' => 'هر چند قدم تأییدشده = ۱ XP'],
        'gamification.goal_xp' => ['value' => 50, 'group' => 'gamification', 'public' => false, 'description' => 'XP رسیدن به هدف روزانه'],

        // Referral
        'referral.qualify_steps' => ['value' => 5000, 'group' => 'referral', 'public' => true, 'description' => 'قدم تأییدشده لازم برای پاداش دعوت'],
        'referral.window_days' => ['value' => 30, 'group' => 'referral', 'public' => true, 'description' => 'مهلت فعال شدن دعوت‌شده (روز)'],
        'referral.referrer_points' => ['value' => 200, 'group' => 'referral', 'public' => true, 'description' => 'پاداش دعوت‌کننده'],
        'referral.referee_points' => ['value' => 100, 'group' => 'referral', 'public' => true, 'description' => 'پاداش دعوت‌شده'],

        // Account
        'account.deletion_grace_days' => ['value' => 14, 'group' => 'account', 'public' => true, 'description' => 'مهلت انصراف از حذف حساب'],
    ],

    'feature_flags' => [
        'store' => ['enabled' => true, 'description' => 'فروشگاه'],
        'money_payment' => ['enabled' => false, 'description' => 'پرداخت ریالی در فروشگاه'],
        'cash_conversion' => ['enabled' => true, 'description' => 'نمایش ارزش ریالی امتیاز'],
        'sponsored_locations' => ['enabled' => true, 'description' => 'مکان‌های اسپانسری'],
        'ads' => ['enabled' => false, 'description' => 'تبلیغات'],
        'rewarded_ads' => ['enabled' => false, 'description' => 'تبلیغ جایزه‌دار'],
        'referral' => ['enabled' => true, 'description' => 'دعوت از دوستان'],
        'health_connect' => ['enabled' => false, 'description' => 'اتصال به Health Connect'],
        'ios' => ['enabled' => false, 'description' => 'پشتیبانی iOS'],
    ],

    'integrity' => [
        // Google Cloud project number + service account used to decode Play Integrity tokens server-side.
        'driver' => env('INTEGRITY_DRIVER', 'null'),
        'package_name' => env('ANDROID_PACKAGE_NAME', 'ir.gamyar.app'),
        'credentials' => env('PLAY_INTEGRITY_CREDENTIALS'),
    ],

    'push' => [
        'driver' => env('PUSH_DRIVER', 'log'),
        'fcm_credentials' => env('FCM_CREDENTIALS'),
    ],

    'leaderboard' => [
        'driver' => env('LEADERBOARD_DRIVER', 'redis'),
        'top' => 50,
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'kavenegar' => [
            'api_key' => env('KAVENEGAR_API_KEY'),
            'template' => env('KAVENEGAR_OTP_TEMPLATE', 'otp'),
        ],
    ],
];
