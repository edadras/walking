# ۳. ساختار پوشه‌ها

## ۳.۱ Monorepo

```
walking/
├── backend/          Laravel 13 — API، Admin Panel، Sponsor Panel، Workers
├── mobile/           Flutter — اپ Android (معماری آماده iOS)
└── docs/             اسناد معماری و Phaseها
```

## ۳.۲ Laravel (Domain-oriented، بدون Overengineering)

Laravel استاندارد حفظ می‌شود (`app/Models`، `app/Http`، …) تا ابزارها و توسعه‌دهندگان جدید سردرگم نشوند؛ **Business Logic** در `app/Domain/<Domain>` قرار می‌گیرد.

```
backend/app/
├── Domain/
│   ├── Auth/
│   │   ├── Actions/            RequestOtp, VerifyOtp, Logout
│   │   ├── Contracts/          SmsSender
│   │   ├── Sms/                LogSmsSender, KavenegarSmsSender
│   │   └── OtpService.php
│   ├── Device/
│   │   ├── Actions/            RegisterDevice, RotateDeviceKey
│   │   ├── Integrity/          PlayIntegrityVerifier (interface + impl)
│   │   ├── RequestSignature.php   canonical string + verify
│   │   └── NonceStore.php
│   ├── User/                   ProfileService, AccountDeletion
│   ├── Activity/               SubmitWalkingSession, DailyActivityAggregator, CalorieEstimator
│   ├── Fraud/
│   │   ├── Rules/              هر Rule یک کلاس (CadenceCeilingRule, TeleportRule, …)
│   │   ├── Contracts/FraudRule.php
│   │   ├── FraudEngine.php     اجرای Ruleها → Confidence / Risk / verified steps
│   │   └── Data/               SessionContext, RuleResult (DTO)
│   ├── Reward/                 RewardEngine, RuleResolver, Data/RewardBreakdown
│   ├── Wallet/                 WalletService (credit/debit/hold/release), ConversionRate
│   ├── Gamification/           XpService, AchievementEvaluator, StreakService
│   ├── Leaderboard/            LeaderboardService (Redis ZSET)
│   ├── Challenge/
│   ├── Sponsor/                Campaign, Visit, Geofence, RotatingQr
│   ├── Coupon/
│   ├── Advertising/
│   │   ├── Contracts/AdProvider.php
│   │   └── Providers/          InternalAdProvider, YektanetAdProvider, AdSellAdProvider
│   ├── Store/                  PurchaseService, Stock
│   ├── Order/
│   ├── Notification/
│   ├── Support/
│   ├── Analytics/
│   ├── Audit/                  AuditLogger
│   └── Settings/               SettingsRepository (cached), FeatureFlags
├── Enums/                      UserStatus, DeviceStatus, TransactionType, …
├── Events/ · Listeners/ · Jobs/
├── Filament/
│   ├── Admin/                  Resources, Pages, Widgets برای /admin
│   └── Sponsor/                Resources, Pages, Widgets برای /sponsor
├── Http/
│   ├── Controllers/Api/V1/     Controllerهای نازک
│   ├── Middleware/             VerifyDeviceSignature, EnsureUserIsActive, FeatureEnabled
│   ├── Requests/Api/V1/        Form Requestها
│   └── Resources/V1/           API Resourceها
├── Models/
├── Policies/
├── Providers/
└── Support/                    ApiResponse, Jalali helpers, Money/Points formatting

backend/routes/
├── api.php                     → include api_v1.php با prefix v1
├── api_v1.php
└── console.php                 Scheduler

backend/tests/
├── Unit/                       Fraud rules، Reward calc، Calorie، Signature
└── Feature/Api/V1/             Auth، Device، Wallet، Double reward، Replay، Concurrency
```

**قواعد:**
- Controller فقط: Validate (Form Request) → فراخوانی Action/Service → Resource.
- Repository فقط وقتی لازم است (مثل Settings با Cache). Eloquent خودش Repository است.
- DTOها `readonly class` هستند.
- هر عملیات تغییر‌دهنده Point فقط از `WalletService`.

## ۳.۳ Flutter (Feature-first)

```
mobile/
├── android/app/src/main/kotlin/.../
│   ├── MainActivity.kt
│   ├── DeviceKeyChannel.kt       Keystore: generate / sign / public key
│   └── StepSensorChannel.kt      Step Counter / Detector (Phase 2)
├── lib/
│   ├── main.dart
│   ├── app/
│   │   ├── app.dart              MaterialApp.router، Theme، Locale، Directionality
│   │   ├── router.dart           go_router + guardها
│   │   └── bootstrap.dart        init storage، device registration، error handlers
│   ├── core/
│   │   ├── config/               Env (baseUrl، flavor)
│   │   ├── network/              ApiClient، SigningInterceptor، AuthInterceptor، ApiException، error mapping فارسی
│   │   ├── security/             DeviceKey (platform channel)، DeviceIdentity
│   │   ├── storage/              SecureStore، LocalDatabase (sqflite)، OfflineQueue
│   │   ├── theme/                tokens (color, type, spacing, radius, motion)، AppTheme (light/dark)
│   │   ├── localization/         AppStrings (fa)، ساختار ARB برای زبان‌های بعدی
│   │   ├── permissions/          PermissionPrimer (توضیح فارسی قبل از درخواست)
│   │   ├── sensors/              (Phase 2)
│   │   ├── analytics/            AnalyticsTracker (batch به سرور)
│   │   ├── format/               ارقام فارسی، Jalali، جداکننده هزارگان
│   │   └── widgets/              Design System Components: AppButton، AppCard، Skeleton، EmptyState، ErrorState، StepRing، PathMotif، AppBottomNav
│   └── features/
│       ├── onboarding/
│       ├── auth/                 data (repository) · application (controllers/providers) · presentation (pages/widgets)
│       ├── home/
│       ├── activity/
│       ├── health/
│       ├── wallet/
│       ├── rewards/
│       ├── leaderboard/
│       ├── challenges/
│       ├── store/
│       ├── sponsors/
│       └── profile/
└── test/                         unit + widget
    integration_test/
```

هر Feature سه لایه دارد و فقط در صورت نیاز:
- `data/` — Repository + Model (fromJson). تنها لایه‌ای که ApiClient را می‌شناسد.
- `application/` — Riverpod Notifier/Provider ها (State).
- `presentation/` — صفحه‌ها و ویجت‌ها. فقط Provider را می‌بیند.
