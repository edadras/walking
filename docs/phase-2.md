# Phase 2 — Step Tracking و Walking Sessions

## خروجی

| بخش | وضعیت |
|-----|-------|
| Migrationها: `walking_sessions`, `activity_samples`, `daily_activities`, `analytics_events` | ✅ |
| `POST /walking-sessions` و `/walking-sessions/batch` (امضاشده) | ✅ |
| Idempotency با `client_session_id` + Hash محتوا؛ ارسال دوباره = همان نتیجه، محتوای متفاوت = `session_conflict` | ✅ |
| `sequence` اکیداً صعودی per device زیر قفل ردیف (`sequence_replayed`) | ✅ |
| محدودیت زمانی: آینده ممنوع، حداکثر ۷ روز آفلاین، عبور از نیمه‌شب محلی ممنوع | ✅ |
| یکپارچگی Bucketها (پوشش بدون همپوشانی، جمع = raw_steps، سقف فیزیکی ۳۶۰ قدم/دقیقه) | ✅ |
| ثبت همپوشانی زمانی با Sessionهای دیگر همین کاربر روی هر دستگاه (`overlap_s`) — مصرف در Fraud Engine فاز ۳ | ✅ |
| تخمین سمت سرور مسافت و کالری (مدل مستند در `ActivityEstimator`) | ✅ |
| `daily_activities` با بازمحاسبه کامل (Idempotent) | ✅ |
| `GET /home` تجمیعی با Cache، `GET /activity/day`، `GET /activity/daily`، لیست و جزئیات Session | ✅ |
| Analytics داخلی با Whitelist رویداد و حذف کلیدهای شخصی | ✅ |
| Retention: `retention:prune` روزانه (Bucketها ۳۰ روز، Analytics ۱۸۰ روز، OTP ۱ روز) | ✅ |
| پنل: جلسه‌های پیاده‌روی (فقط‌خواندنی)، فعالیت روزانه کاربر، «قدم‌های امروز» در داشبورد؛ زمان‌ها به وقت تهران | ✅ |
| Android: خوانش دوره‌ای Step Counter با WorkManager، Activity Transition، Foreground Service نوع `health` برای پیاده‌روی فعال (GPS اختیاری با LocationManager) | ✅ |
| Flutter: ساخت Passive Session از خوانش‌ها، صف آفلاین SQLite با Sequence، Sync دسته‌ای امضاشده، Home زنده، صفحه فعالیت، صفحه پیاده‌روی، جزئیات Session، Permission Primer | ✅ |

## جریان داده

```
Step Counter (HW) ──WorkManager 15m──▶ StepStore (readings + boot count)
Activity Recognition ─transitions───▶ StepStore
                                           │ (app open / resume / هر ۵ دقیقه)
                                           ▼
                       PassiveSessionBuilder (Dart, منطقه زمانی حساب)
                                           ▼
Active walk (FGS) ── minute buckets ──▶ SessionQueue (SQLite, sequence)
                                           ▼
                        POST /walking-sessions/batch (ECDSA signed)
                                           ▼
                 SubmitWalkingSession → daily_activities → WalkingSessionSubmitted
```

- خوانش‌ها فقط **بعد** از ذخیره امن Sessionها در صف Ack می‌شوند؛ قطع شدن برنامه در هر لحظه داده را از بین نمی‌برد و دوبار هم نمی‌شمارد.
- فاصله بین `active_start` و `active_end` از پنجره‌های Passive حذف می‌شود؛ قدم‌های پیاده‌روی فعال دوبار شمرده نمی‌شوند.
- Reboot: کاهش شمارنده یا تغییر `BOOT_COUNT` → مقدار جدید = قدم از زمان روشن شدن.

## تست‌ها

- Backend: ۷۰ تست — Idempotency، Conflict، Replay/Reorder با Sequence، امضای الزامی، آینده/کهنه/نیمه‌شب، یکپارچگی Bucket، Batch با نتیجه جداگانه، همپوشانی بین دو دستگاه، ایزوله بودن داده کاربران، Home/Day/Daily، تازه شدن Cache، تخمین‌گر، Analytics، Retention، پنل.
- Flutter: ۴۳ تست — Builder (Reboot، پیاده‌روی فعال، نیمه‌شب، شکاف طولانی، برچسب خودرو)، صف (Sequence یکنوا)، Sync (آفلاین/تکراری/ردشده/اجرای همزمان)، Mapper پیاده‌روی فعال، Home و Activity.
- **Contract test** دوطرفه: `contracts/walking_session_batch.json` را Flutter تولید و مقایسه می‌کند و Laravel همان فایل را ارسال و می‌پذیرد.

## تصمیم‌ها و محدودیت‌ها

- **Verified steps هنوز خالی است.** تا Fraud Engine (فاز ۳) همه Sessionها `submitted` هستند؛ UI عدد «در حال بررسی» را جدا نشان می‌دهد.
- **Sync فقط وقتی برنامه باز است** (باز شدن، بازگشت به برنامه، هر ۵ دقیقه). شمارش در پس‌زمینه ادامه دارد و چیزی گم نمی‌شود، اما اگر کاربر بیش از ۷ روز برنامه را باز نکند، قدیمی‌ترین پنجره‌ها رد می‌شوند. Sync پس‌زمینه (Dart isolate از WorkManager) در فاز ۹ اضافه می‌شود.
- پنجره‌های Passive بلند (مثلاً وقتی WorkManager توسط OEM متوقف شده) به‌صورت یکنواخت بین Bucketهای ۶۰ دقیقه‌ای تقسیم می‌شوند؛ جمع قدم دقیق است، توزیع زمانی تقریبی است و تأییدشان در فاز ۳ محتاطانه‌تر خواهد بود.
- `permission_handler_android` روی 13.0.1 Pin شده چون نسخه 14 به compileSdk 37 نیاز دارد که AGP فعلی پشتیبانی نمی‌کند.
- کالری «خالص فعالیت» (بدون متابولیسم پایه) نمایش داده می‌شود و همه‌جا «تخمینی» است.
