# ۷. Background Tracking و Offline Sync

## ۷.۱ واقعیت‌های Android (Android 10 تا 16)

| موضوع | واقعیت | پیامد طراحی |
|-------|--------|-------------|
| `TYPE_STEP_COUNTER` | سنسور سخت‌افزاری کم‌مصرف؛ شمارنده تجمعی از زمان boot؛ در Doze هم می‌شمارد | منبع اصلی شمارش؛ نیازی به سرویس دائمی نیست |
| `TYPE_STEP_DETECTOR` | رویداد به ازای هر قدم با timestamp؛ فقط وقتی listener فعال است | فقط در Session فعال و برای مقایسه با Counter |
| مجوز `ACTIVITY_RECOGNITION` | Runtime permission از Android 10 | قبل از درخواست، صفحه توضیح فارسی (Permission Primer) |
| Foreground Service | Android 14+: نوع اجباری. `health` نیازمند `FOREGROUND_SERVICE_HEALTH` + (`ACTIVITY_RECOGNITION` یا `BODY_SENSORS`)؛ `location` نیازمند `FOREGROUND_SERVICE_LOCATION` + مجوز Location | FGS فقط برای Session فعال که کاربر شروع کرده؛ نوع `health` و در صورت GPS `health\|location` |
| WorkManager | حداقل بازه دوره‌ای ۱۵ دقیقه؛ Doze و App Standby Bucket آن را به تأخیر می‌اندازند | تأخیر فقط زمان Sync را عقب می‌اندازد، نه دقت شمارش (Counter تجمعی است) |
| Reboot | Counter صفر می‌شود | اگر مقدار فعلی < آخرین مقدار: baseline جدید؛ `BOOT_COMPLETED` برای ثبت baseline |
| Geofencing API | در پس‌زمینه نیازمند `ACCESS_BACKGROUND_LOCATION` (مجوز پرریسک در Google Play Policy) | **استفاده نمی‌شود.** Visit فقط با اپ باز در صفحه Location انجام می‌شود (Foreground location) |
| Activity Recognition Transition API | Transitionهای `WALKING/RUNNING/IN_VEHICLE/STILL` با PendingIntent، کم‌مصرف | سیگنال کلیدی ضد «قدم در خودرو» برای پنجره‌های Passive (نیازمند Google Play Services؛ در نبود آن سیگنال خالی است) |
| OEM battery killers | Xiaomi/Huawei/Samsung ممکن است Worker را متوقف کنند | راهنمای اختیاری «بهینه‌سازی باتری» در تنظیمات؛ هیچ درخواست اجباری `REQUEST_IGNORE_BATTERY_OPTIMIZATIONS` |

## ۷.۲ دو حالت ردیابی

### Passive (پیش‌فرض، همیشه)
- `StepCounterWorker` (WorkManager periodic، ۱۵ دقیقه، بدون شرط شبکه):
  1. یک‌بار listener روی `STEP_COUNTER` ثبت، اولین مقدار را می‌گیرد (Timeout ۵ ثانیه)، unregister.
  2. `delta = current − last` (مدیریت reboot) و یک **Window** `[last_read_at, now]` در SQLite محلی.
  3. Transitionهای Activity Recognition در این بازه ضمیمه می‌شوند.
- Windowها در **Passive Session** حداکثر ۶۰ دقیقه‌ای ادغام و به Queue اضافه می‌شوند.
- Verification برای Passive «درشت‌دانه» است (نرخ متوسط در پنجره، Transition خودرو، سقف روزانه). Admin می‌تواند سقف Reward پنجره‌های Passive را جدا تنظیم کند.

### Active (Walking Session)
- کاربر «شروع پیاده‌روی» را می‌زند → Foreground Service با Notification دائمی.
- Listener روی `STEP_COUNTER` و `STEP_DETECTOR` با `maxReportLatency` (batching سخت‌افزاری برای کاهش wake-up)، شتاب‌سنج با نرخ پایین (`SENSOR_DELAY_NORMAL`) و پردازش **روی دستگاه** به آمار دقیقه‌ای (std، peak frequency با zero-crossing).
- GPS **اختیاری**: فقط اگر کاربر «ثبت مسیر» را فعال کند یا Challenge مکانی باشد؛ `PRIORITY_BALANCED_POWER_ACCURACY` و بازه ۱۰–۳۰ ثانیه تطبیقی؛ فقط خلاصه (مسافت، سرعت‌ها، jumpها، mock flag) ارسال می‌شود، نه مسیر خام.
- پایان: Session با Minute Buckets کامل → Queue.

### Health Connect (آینده، Feature Flag `health_connect`)
- `HealthDataSource` یک Interface در `core/sensors` است؛ `SensorStepSource` و `HealthConnectStepSource` پیاده‌سازی‌های آن هستند. داده Health Connect با `source = health_connect` و Confidence متفاوت (نمی‌توان سیگنال حرکتی را دید) پردازش می‌شود.

## ۷.۳ Offline Sync

```
Local SQLite: pending_sessions
  id (client_session_id) · sequence · payload JSON · created_at · attempts · last_error · status (queued, sending, accepted, rejected)
```

1. هر Session پس از بسته شدن با `sequence` یکنواخت per device (از SecureStorage، هرگز کاهش نمی‌یابد) در صف قرار می‌گیرد.
2. Sync هنگام باز شدن اپ، بازگشت به اپ و هر ۵ دقیقه در Foreground (نسخه ۱؛ `SyncWorker` پس‌زمینه در فاز ۹) تا ۲۰ Session در یک `POST /walking-sessions/batch` می‌فرستد. **امضا در لحظه ارسال** ساخته می‌شود (timestamp تازه)، نه در لحظه ضبط.
3. پاسخ per-item: `accepted`/`duplicate` → حذف از صف؛ `rejected` با کد → حذف و ثبت محلی؛ خطای شبکه → Backoff نمایی.
4. سرور:
   - `client_session_id` تکراری → `duplicate` (Idempotent، بدون خطا).
   - `sequence <= device.last_sequence` برای Session جدید → رد (Replay/Reorder). پذیرش sequence با فاصله (gap) مجاز است (Session گم‌شده).
   - `ended_at` در آینده → رد؛ `started_at` قدیمی‌تر از `offline_max_age_days` (پیش‌فرض ۷) → رد.
   - همپوشانی زمانی با Session دیگر همین کاربر → بخش همپوشان حذف.
   - Session قدیمی‌تر از ۴۸ ساعت: Reward به **روز محلی خودش** تعلق می‌گیرد و سقف‌های همان روز اعمال می‌شود؛ بنابراین ذخیره آفلاین و ارسال یکجا هیچ مزیتی برای دور زدن سقف ندارد.
5. هیچ مقدار Reward یا Balance در صف محلی وجود ندارد؛ دستکاری صف (روی دستگاه روت‌شده) فقط به داده‌ای می‌رسد که Fraud Engine دوباره ارزیابی می‌کند.

## ۷.۴ مصرف باتری — بودجه

| حالت | هدف |
|------|-----|
| Passive | < ۱٪ باتری در ۲۴ ساعت (یک wake-up کوتاه هر ۱۵+ دقیقه) |
| Active بدون GPS | < ۳٪ در ساعت |
| Active با GPS | < ۶٪ در ساعت |
