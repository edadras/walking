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
        'security.require_integrity' => ['value' => false, 'group' => 'security', 'public' => false, 'description' => 'نصب از Google Play باید Play Integrity معتبر داشته باشد؛ نصب از بازار/مایکت/مستقیم فقط با نتیجه صریحاً ناموفق رد می‌شود'],
        'security.timezone_change_cooldown_days' => ['value' => 7, 'group' => 'security', 'public' => false, 'description' => 'حداقل فاصله تغییر منطقه زمانی'],

        // App
        'app.min_supported_version' => ['value' => '1.0.0', 'group' => 'app', 'public' => true, 'description' => 'حداقل نسخه قابل استفاده'],
        'app.latest_version' => ['value' => '1.0.0', 'group' => 'app', 'public' => true, 'description' => 'آخرین نسخه منتشرشده'],
        'app.support_phone' => ['value' => null, 'group' => 'app', 'public' => true, 'description' => 'شماره پشتیبانی'],

        // Map (nearby rewards). Direct tile URL for keyless providers; keyed providers go through MAP_TILE_UPSTREAM.
        'map.tile_url' => ['value' => null, 'group' => 'app', 'public' => true, 'description' => 'آدرس Tile نقشه با {z}/{x}/{y} (خالی = پیش‌فرض اپ)'],
        'map.attribution' => ['value' => 'OpenStreetMap', 'group' => 'app', 'public' => true, 'description' => 'منبع نقشه (نمایش روی نقشه)'],
        'map.max_zoom' => ['value' => 18, 'group' => 'app', 'public' => true, 'description' => 'حداکثر بزرگ‌نمایی نقشه'],

        // Cash-out (behind the `cashout` flag). Points are debited at request time and refunded on rejection.
        'cashout.min_points' => ['value' => 5000, 'group' => 'cashout', 'public' => true, 'description' => 'حداقل امتیاز هر درخواست برداشت'],
        'cashout.max_points_per_request' => ['value' => 50000, 'group' => 'cashout', 'public' => true, 'description' => 'حداکثر امتیاز هر درخواست برداشت'],
        'cashout.max_points_per_30_days' => ['value' => 150000, 'group' => 'cashout', 'public' => true, 'description' => 'سقف برداشت در ۳۰ روز'],
        'cashout.min_account_age_days' => ['value' => 30, 'group' => 'cashout', 'public' => true, 'description' => 'حداقل عمر حساب برای برداشت (روز)'],
        'cashout.maturity_days' => ['value' => 14, 'group' => 'cashout', 'public' => true, 'description' => 'امتیاز پس از چند روز قابل برداشت می‌شود'],
        'cashout.eligible_types' => ['value' => 'walking_reward,goal_bonus,streak_bonus,challenge_reward,quest_reward,achievement_reward,sponsor_reward,coupon_reward', 'group' => 'cashout', 'public' => false, 'description' => 'منابع امتیاز قابل برداشت (با کاما؛ دعوت، تبلیغ و اصلاح دستی عمداً نیستند)'],
        'cashout.daily_budget_rial' => ['value' => 0, 'group' => 'cashout', 'public' => false, 'description' => 'سقف کل تأیید برداشت در روز (ریال؛ ۰ = بدون سقف)'],
        'cashout.monthly_budget_rial' => ['value' => 0, 'group' => 'cashout', 'public' => false, 'description' => 'سقف کل تأیید برداشت در ماه شمسی (ریال؛ ۰ = بدون سقف)'],
        'cashout.kyc_retention_days' => ['value' => 1825, 'group' => 'cashout', 'public' => false, 'description' => 'نگهداری مدارک هویت برداشت پس از حذف حساب (روز؛ طبق نظر حقوقی تنظیم شود)'],
        'cashout.min_age_years' => ['value' => 18, 'group' => 'cashout', 'public' => true, 'description' => 'حداقل سن برای برداشت'],

        // Streak freeze: bought with points, covers one missed day automatically.
        'streak.freeze_price' => ['value' => 300, 'group' => 'gamification', 'public' => true, 'description' => 'قیمت محافظ زنجیره (امتیاز)'],
        'streak.freeze_max_owned' => ['value' => 2, 'group' => 'gamification', 'public' => true, 'description' => 'حداکثر محافظ ذخیره‌شده'],

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

        // Sponsored location visits
        'visits.max_accuracy_m' => ['value' => 80, 'group' => 'visits', 'public' => true, 'description' => 'حداکثر خطای GPS قابل قبول (متر)'],
        'visits.accuracy_allowance_m' => ['value' => 30, 'group' => 'visits', 'public' => false, 'description' => 'حداکثر خطای GPS که به شعاع مکان اضافه می‌شود (متر)'],
        'visits.ping_interval_s' => ['value' => 30, 'group' => 'visits', 'public' => true, 'description' => 'فاصله ارسال موقعیت در حین بازدید (ثانیه)'],
        'visits.max_ping_gap_s' => ['value' => 120, 'group' => 'visits', 'public' => false, 'description' => 'بیشترین فاصله دو Ping که زمان حضور محسوب می‌شود (ثانیه)'],
        'visits.max_speed_mps' => ['value' => 45, 'group' => 'visits', 'public' => false, 'description' => 'سرعت جابه‌جایی غیرممکن بین دو Ping (متر بر ثانیه)'],
        'visits.expire_minutes' => ['value' => 20, 'group' => 'visits', 'public' => false, 'description' => 'انقضای بازدید بدون Ping (دقیقه)'],
        'visits.qr_window_s' => ['value' => 30, 'group' => 'visits', 'public' => false, 'description' => 'طول هر پنجره QR چرخشی (ثانیه)'],
        'visits.nearby_max_km' => ['value' => 20, 'group' => 'visits', 'public' => false, 'description' => 'حداکثر شعاع جستجوی مکان‌های اطراف (کیلومتر)'],

        // Advertising
        'ads.default_frequency_cap' => ['value' => 6, 'group' => 'ads', 'public' => false, 'description' => 'حداکثر نمایش هر کمپین تبلیغ به یک کاربر در روز (پیش‌فرض)'],
        'ads.rewarded_daily_cap' => ['value' => 3, 'group' => 'ads', 'public' => true, 'description' => 'حداکثر تبلیغ جایزه‌دار برای هر کاربر در روز'],
        'ads.rewarded_max_age_minutes' => ['value' => 10, 'group' => 'ads', 'public' => false, 'description' => 'مهلت تکمیل تبلیغ جایزه‌دار پس از شروع (دقیقه)'],
        'ads.rewarded_max_points' => ['value' => 20, 'group' => 'ads', 'public' => false, 'description' => 'سقف امتیاز هر تبلیغ جایزه‌دار'],

        // Device keys
        'security.device_key_max_age_days' => ['value' => 180, 'group' => 'security', 'public' => true, 'description' => 'عمر کلید دستگاه تا تعویض خودکار (روز)'],

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
        'quests' => ['enabled' => true, 'description' => 'مأموریت‌های روزانه و هفتگی'],
        'friends' => ['enabled' => true, 'description' => 'دوستان و چالش گروهی'],
        'cashout' => ['enabled' => false, 'description' => 'برداشت نقدی امتیاز به حساب بانکی'],
        'health_connect' => ['enabled' => false, 'description' => 'اتصال به Health Connect'],
        'ios' => ['enabled' => false, 'description' => 'پشتیبانی iOS'],
    ],

    // Only honoured outside production and only for +98999… numbers (loadtest/README.md).
    'loadtest' => [
        'otp_code' => env('LOADTEST_OTP_CODE'),
    ],

    'ops' => [
        // Long queue waits / failed jobs (Horizon) are mailed here.
        'alert_email' => env('OPS_ALERT_EMAIL'),
        // Chat webhook for alerts, e.g. Mattermost/Rocket.Chat incoming webhook (POST {"text": ...}).
        'alert_webhook' => env('OPS_ALERT_WEBHOOK'),
    ],

    'security' => [
        // Admin panel requires TOTP. Only switch off for local development and the test suite.
        'admin_mfa_required' => env('ADMIN_MFA_REQUIRED', true),
        // Allowed certificate SPKI pins are shipped in the app (--dart-define); listed here for ops reference.
        'hsts_max_age' => (int) env('HSTS_MAX_AGE', 31536000),
        // Keyed hashes of national codes, Sheba numbers and IPs. Independent of APP_KEY so rotating
        // APP_KEY (with APP_PREVIOUS_KEYS) never breaks uniqueness checks or IP clustering.
        'pii_hash_key' => env('PII_HASH_KEY'),
    ],

    'integrity' => [
        // Google Cloud project number + service account used to decode Play Integrity tokens server-side.
        'driver' => env('INTEGRITY_DRIVER', 'null'),
        'package_name' => env('ANDROID_PACKAGE_NAME', 'ir.gamyar.app'),
        'credentials' => env('PLAY_INTEGRITY_CREDENTIALS'),
    ],

    // Each provider is on when configured; devices say which one they registered with.
    'push' => [
        'fcm_credentials' => env('FCM_CREDENTIALS'),
        'pushe_token' => env('PUSHE_API_TOKEN'),
        'pushe_app_id' => env('PUSHE_APP_ID'),
    ],

    // Rial payments (store, behind the money_payment flag). Bound only when a merchant id is set.
    'payments' => [
        'zarinpal' => [
            'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
            'sandbox' => (bool) env('ZARINPAL_SANDBOX', false),
        ],
    ],

    // Server-side tile proxy for providers that need a secret key (Map.ir, Neshan…):
    // the key stays on the server and tiles are cached. Template uses {z}/{x}/{y}.
    'map' => [
        'upstream' => env('MAP_TILE_UPSTREAM'),
        'upstream_headers' => env('MAP_TILE_UPSTREAM_HEADERS'), // "Header: value; Other: value"
        'cache_days' => (int) env('MAP_TILE_CACHE_DAYS', 14),
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
            'cashout_template' => env('KAVENEGAR_CASHOUT_TEMPLATE'),  // «کد تأیید برداشت: %token»
        ],
    ],
];
