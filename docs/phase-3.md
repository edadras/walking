# Phase 3 — Fraud Engine، Reward Engine، Ledger و Wallet

## خروجی

| بخش | وضعیت |
|-----|-------|
| جدول‌ها: `fraud_rules`, `fraud_events`, `fraud_cases`, `point_conversion_rates`, `wallets`, `point_transactions`, `reward_rules`, `rewards` | ✅ |
| Fraud Engine چندسیگناله با ۱۵ Rule (پارامتر، وزن و فعال/غیرفعال از پنل) | ✅ |
| Verified Steps، Fraud Risk و Walking Confidence برای هر Session | ✅ |
| پرونده تقلب خودکار برای ریسک بالا + تصمیم تحلیلگر (تأیید، امن، علامت، رد، مسدودسازی) با Audit | ✅ |
| Reward Engine سمت سرور: نرخ، سقف قدم روزانه، سقف امتیاز روزانه/هفتگی، ضریب روز هفته/ساعت/بازه، پاداش هدف روزانه | ✅ |
| Point Ledger غیرقابل تغییر (فقط انتقال pending → completed/reversed) با موجودی قبل/بعد و Snapshot نرخ ریال | ✅ |
| `WalletService`: hold / release / reverse / credit / debit / adjust — قفل ردیف، Idempotency، Retry روی Deadlock | ✅ |
| دوره نگهداری امتیاز (پیش‌فرض ۲۴ ساعت) و آزادسازی زمان‌بندی‌شده؛ کاربر دارای پرونده باز یا مسدود آزاد نمی‌شود | ✅ |
| API: `/wallet`، `/wallet/transactions?filter=`، `/rewards`، `/rewards/{id}`؛ Home با امتیاز امروز و کیف پول | ✅ |
| پنل: قوانین تقلب، پرونده‌ها با شواهد، قوانین پاداش، نرخ تبدیل (Append-only)، تراکنش‌های کاربر، اصلاح امتیاز با دلیل، آمار داشبورد | ✅ |
| Flutter: کیف پول (موجودی، ارزش ریالی، در حال بررسی، تاریخچه با فیلتر و صفحه‌بندی)، مرکز جایزه، کارت امتیاز در Home، اطمینان در جزئیات Session | ✅ |

## جریان

```
WalkingSessionSubmitted ──(queue: fraud)──▶ FraudEngine::score
     status/verified/risk/confidence + fraud_events (+ fraud_case اگر ریسک ≥ آستانه بررسی)
WalkingSessionScored ──(queue: critical)──▶ RewardEngine::forSession
     rewards + WalletService::hold (available_at = now + hold_hours)
Scheduler هر ۱۰ دقیقه ──▶ wallet:release-pending ──▶ WalletService::release
```

## Ruleها

`cadence_ceiling`، `impossible_rate`، `detector_mismatch`، `motion_signature`، `metronome_pattern`، `vehicle_speed`، `gps_teleport`، `mock_location`، `device_integrity`، `clock_skew`، `overlapping_session`، `daily_physiological_cap`، `multi_account_device`، `multi_device_account`، `repeat_offender`.

هر Rule یا ریسک اضافه می‌کند، یا سقف قدم (Bucket یا Session) می‌گذارد. هیچ Rule به‌تنهایی تصمیم نمی‌گیرد. افزودن مدل ML در آینده یعنی یک کلاس جدید که `FraudRule` را پیاده می‌کند. هر تغییر در پنل نسخه قوانین (`fraud.rule_set_version`) را بالا می‌برد و این نسخه روی Session ثبت می‌شود.

## تست‌ها

- Backend: ۱۰۹ تست. شامل:
  - **Double spending واقعی:** ۸ Process جداگانه PHP با اتصال DB مستقل روی یک موجودی رقابت می‌کنند؛ دقیقاً ۳ خرید ۳۰ امتیازی از ۱۰۰ موفق می‌شود.
  - ارسال هم‌زمان یک درخواست تکراری فقط یک بار اعمال می‌شود.
  - برابری همیشگی موجودی کیف پول با جمع Ledger، تغییرناپذیری Ledger، و Reward فقط یک بار برای هر Session.
  - محاسبه روی مجموع روز (بدون از دست رفتن امتیاز در گرد کردن)، سقف‌ها، ضریب جمعه و پاداش هدف یک‌باره.
  - Ruleها (حذف دقیقه غیرممکن، خودرو، دستگاه لرزاننده → بررسی، Mock+Integrity → رد، دستگاه بدون Integrity → سقف، همپوشانی، چند حساب)، و غیرفعال کردن Rule از پنل.
  - جریان کامل API: پیاده‌روی ← امتیاز در حال بررسی ← آزادسازی بعد از ۲۴ ساعت؛ تأیید/رد/مسدودسازی پرونده.
- Flutter: ۴۵ تست (کیف پول، فیلترها، تراکنش لغوشده، مرکز جایزه).

## باگ‌هایی که تست‌ها پیدا کردند

- **Deadlock در کیف پول هنگام خرید هم‌زمان:** `INSERT IGNORE` روی ردیف موجود قفل اشتراکی می‌گیرد و با `FOR UPDATE` بعدی بن‌بست می‌سازد. رفع: اول قفل و فقط در نبود ردیف درج، به‌علاوه Retry خودکار Transaction.
- **Cache اشیاء در Laravel 13:** Unserialize کردن Model از Cache غیرفعال است (امن‌تر)؛ Ruleها به‌صورت آرایه Cache می‌شوند و کلید Cache نسخه‌دار است.
- **Skeleton داخل لیست:** Skeleton پیش‌فرض داخل صفحات اسکرول‌دار خطای Layout می‌داد.
