# استقرار و عملیات

## معماری اجرا

```
                 ┌────────────┐
   App / Panels ─▶│  nginx     │── static (public/, uploads)
                 └─────┬──────┘
                       │ FastCGI
                 ┌─────▼──────┐        ┌──────────────┐
                 │ app (fpm)  │───────▶│  MySQL 8.4   │
                 └─────┬──────┘        └──────────────┘
                       │               ┌──────────────┐
      horizon ─────────┼──────────────▶│  Redis 7     │  cache · session · queue · leaderboard · nonce
      scheduler ───────┘               └──────────────┘
```

یک Image (`backend/Dockerfile`, Target `app`) سه نقش دارد: `php-fpm`، `php artisan horizon`، `php artisan schedule:work`. Target `web` همان `public/` را در nginx می‌گذارد تا فایل‌های Filament/Horizon همیشه با کد هم‌نسخه باشند.

## اجرای تک‌سرور (Staging / شروع Production)

```bash
cp deploy/docker/.env.example deploy/docker/.env    # مقادیر محرمانه را پر کنید؛ هرگز Commit نکنید
docker compose -f deploy/docker/compose.yml --env-file deploy/docker/.env up -d --build
```

سرویس `migrate` در هر استقرار یک بار اجرا می‌شود: `migrate --force` → `PlatformSeeder` (Idempotent: Flagها، Ruleها، سطوح، دستاوردها، جایگاه‌های تبلیغ، مدیر ارشد از `ADMIN_EMAIL`) → `optimize` (کش config/route/view/event). `app`، `horizon` و `scheduler` فقط پس از موفقیت آن بالا می‌آیند.

TLS را روی Load Balancer یا یک Reverse Proxy جلوی nginx خاتمه دهید و `TRUSTED_PROXIES` را روی شبکه آن تنظیم کنید (برای IP واقعی در Rate Limit و HSTS).

## مقیاس‌پذیری

| لایه | رویکرد |
|------|--------|
| app | بدون حالت (Session/Cache در Redis) → افقی پشت LB. `pm.max_children` در `deploy/fpm-pool.conf` را با RAM هماهنگ کنید (~۶۰MB برای هر Worker). |
| horizon | Supervisorهای `critical` (OTP، پاداش)، `fraud` (امتیازدهی جلسه — CPU-bound) و `general`؛ Autoscale با `time`. برای بار بالا Horizon را روی سرورهای جدا اجرا کنید. |
| scheduler | **فقط یک نمونه** (`onOneServer` + قفل Redis) — چند نمونه امن است ولی بی‌فایده. |
| MySQL | Primary + Replica برای گزارش‌ها؛ `innodb_buffer_pool_size` ≈ ۷۰٪ RAM. Slow log روشن (۰٫۵ ثانیه). |
| Redis | AOF روشن و `noeviction` (صف و Nonce نباید حذف شوند). برای مقیاس بالا Cache و Queue را روی دو Redis جدا ببرید. |

## جدول‌های بزرگ و Partitioning

جدول‌های رشدکننده: `walking_sessions`, `activity_samples`, `point_transactions`, `fraud_events`, `ad_events`, `analytics_events`, `notifications`, `audit_logs`.

- **فعلاً Partition نشده‌اند**: MySQL برای جدول Partitionشده FK و Unique بدون ستون Partition را نمی‌پذیرد و چند جدول بالا FK/Unique دارند (مثلاً `UQ(user_id, idempotency_key)` که ضامن جلوگیری از پاداش تکراری است). حذف این قیدها برای Partition ارزش امنیتی‌اش را ندارد.
- **نگهداری**: `retention:prune` (هر شب) نمونه‌های دقیقه‌ای فعالیت و رویدادهای تحلیلی قدیمی را حذف می‌کند؛ Ledger و Audit هرگز حذف نمی‌شوند.
- **وقتی لازم شد** (بالای ~۵۰۰ میلیون ردیف): `ad_events` و `analytics_events` (بدون FK بحرانی) کاندیدای `PARTITION BY RANGE (TO_DAYS(created_at))` ماهانه‌اند؛ `point_transactions` با Archive سالانه به جدول `point_transactions_archive` (فقط‌خواندنی) کوچک نگه داشته می‌شود. ایندکس‌های بازه تاریخ لازم برای داشبورد و گزارش در `2026_01_09_000300_add_reporting_indexes` اضافه شده‌اند.

## متغیرهای محیطی کلیدی

| متغیر | توضیح |
|-------|-------|
| `APP_KEY` | کلید رمزنگاری (کدهای دیجیتال، Secret شعبه‌ها، TOTP و Payload صف OTP با آن رمز می‌شوند). **از دست رفتن = از دست رفتن کدهای فروشگاه**؛ در Vault نگه دارید. |
| `ADMIN_MFA_REQUIRED` | `true` در همه محیط‌ها به‌جز توسعه محلی. |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | اولین مدیر ارشد (Seeder). پس از اولین ورود TOTP فعال کنید. |
| `SMS_DRIVER=kavenegar` + `KAVENEGAR_*` | ارسال OTP. `KAVENEGAR_CASHOUT_TEMPLATE` (اختیاری) قالب جدا برای کد تأیید برداشت، مثلاً «کد تأیید برداشت وجه: %token — آن را به کسی ندهید»؛ اگر خالی باشد قالب ورود استفاده می‌شود. |
| `INTEGRITY_DRIVER=google` + `PLAY_INTEGRITY_CREDENTIALS` | مسیر فایل JSON حساب سرویس Google Cloud برای رمزگشایی Play Integrity. |
| `FCM_CREDENTIALS` | ارسال Push به نسخه Google Play (فایل JSON حساب سرویس Firebase). |
| `PUSHE_API_TOKEN` + `PUSHE_APP_ID` | ارسال Push به نسخه بازار/مایکت از طریق Pushe (توکن «وب‌سرویس» کنسول Pushe). |
| `ZARINPAL_MERCHANT_ID` (+ `ZARINPAL_SANDBOX=true` در Staging) | پرداخت ریالی فروشگاه. بدون آن پرداخت ریالی خاموش است؛ Flag `money_payment` هم باید روشن شود. |
| `MAP_TILE_UPSTREAM` + `MAP_TILE_UPSTREAM_HEADERS` | Tile نقشه از سرویس ایرانی کلیددار (مثلاً `x-api-key: …`)؛ از طریق Proxy و Cache سرور. بدون کلید: آدرس Tile را در تنظیمات ادمین (`map.tile_url`) بگذارید. |
| `TRUSTED_PROXIES` | شبکه Load Balancer. |
| `OPS_ALERT_EMAIL` | هشدار صف طولانی / Job ناموفق از Horizon. |
| `LOADTEST_OTP_CODE` | **فقط Staging** برای k6؛ در Production کد آن غیرفعال است. |

## ساخت اپ اندروید (Release)

برای هر فروشگاه یک Build جدا ساخته می‌شود: `play`، `bazaar` و `myket`. شناسه بسته در همه یکی است. Flavor تعیین می‌کند اپ از Play Integrity استفاده کند یا نه، و Push آن FCM باشد یا Pushe.

```bash
export API_BASE_URL=https://api.gamyar.ir/api/v1 CERT_PINS=<pin فعلی>,<pin پشتیبان> MAP_TILE_URL=<سرور Tile>
export INTEGRITY_PROJECT_NUMBER=<cloud project number>                      # فقط play
export FCM_API_KEY=... FCM_APP_ID=... FCM_SENDER_ID=... FCM_PROJECT_ID=...  # فقط play
export PUSHE_TOKEN=<توکن مانیفست Pushe>                                      # bazaar و myket
mobile/tool/build_release.sh bazaar      # یا play / myket؛ آرگومان دوم apk برای خروجی APK
```

- **Obfuscate و نمادها:** اسکریپت، Build را Obfuscate می‌کند و نمادها را در `build/symbols/<نسخه>/<فروشگاه>` می‌گذارد. این پوشه را همراه هر انتشار آرشیو کنید؛ بدون آن Stack Trace گزارش‌های خطا خوانا نمی‌شود.
- **خواندن یک گزارش خطا:** از پنل ادمین، صفحه «خطاهای اپ»، گزینه «دانلود Stack» را بزنید و فایل را به این دستور بدهید:

  ```bash
  mobile/tool/symbolize.sh crash.txt <نسخه> <فروشگاه>
  ```

- **Firebase:** مقادیر `FCM_*` از تنظیمات اپ اندروید در کنسول Firebase برداشته می‌شوند. فایل `google-services.json` در مخزن نیست.
- **Pushe:** در Buildهای بازار/مایکت، SDK بومی Pushe جای FCM را می‌گیرد. توکن مانیفست با `PUSHE_TOKEN` داده می‌شود.
- **بدون پیکربندی Push:** اگر هیچ‌کدام تنظیم نشده باشد، Push غیرفعال است و صندوق اعلان داخل اپ همچنان کار می‌کند.

Pin از کلید عمومی گواهی سرور:

```bash
openssl s_client -connect api.gamyar.ir:443 -servername api.gamyar.ir </dev/null 2>/dev/null \
 | openssl x509 -pubkey -noout | openssl pkey -pubin -outform der | openssl dgst -sha256 -binary | base64
```

همیشه **دو Pin** بفرستید: کلید فعلی و کلید بعدی (از قبل ساخته و امن نگه‌داشته‌شده). گواهی را با همان کلید تمدید کنید (`--reuse-key`) و پیش از تعویض کلید، نسخه‌ای از اپ با Pin جدید منتشر کنید. امضای Release از `android/key.properties` (Commit نمی‌شود).

## عملیات روزمره

- **استقرار بدون Downtime**: Image جدید → `migrate` (Migrationها Backward-compatible نوشته شوند) → Rolling restart `app` → `php artisan horizon:terminate` (Horizon با کد جدید بالا می‌آید).
- **پشتیبان‌گیری**: MySQL روزانه کامل + Binlog برای PITR؛ Redis AOF؛ Volume `storage` (آپلودها). بازیابی را هر ماه روی Staging تمرین کنید.
- **پایش**: `/up` (Health)، Horizon (زمان انتظار صف‌ها، Job ناموفق)، Slow query log، داشبورد تقلب پنل. هشدار روی: صف `critical` > ۱۰ ثانیه، `fraud` > ۱۲۰ ثانیه، نرخ خطای ۵xx، رشد ناگهانی `fraud_events`.
- **Rollback**: Image قبلی را اجرا کنید؛ Migrationهای جدید فقط افزایشی‌اند و Rollback کد به Rollback پایگاه‌داده نیاز ندارد.

## چک‌لیست امنیت Production

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`
- [ ] `ADMIN_MFA_REQUIRED=true` و TOTP برای همه مدیران فعال
- [ ] `LOADTEST_OTP_CODE` خالی
- [ ] کاربر پایگاه‌داده برنامه روی `audit_logs` فقط `INSERT, SELECT` (بخش ۶ مستندات امنیت)
- [ ] Redis و MySQL فقط در شبکه داخلی
- [ ] Play Integrity فعال و `security.require_integrity` پس از دوره آزمایشی روشن
- [ ] `CERT_PINS` در Build Release
- [ ] `FCM_*` (play) و `PUSHE_TOKEN` (bazaar/myket) در Build Release؛ `FCM_CREDENTIALS` و `PUSHE_API_TOKEN`/`PUSHE_APP_ID` روی سرور؛ یک Push آزمایشی روی گوشی واقعی از هر فروشگاه
- [ ] نمادهای Obfuscation هر انتشار آرشیو شده است (`build/symbols/<نسخه>/<فروشگاه>`)
- [ ] پرداخت ریالی: یک خرید آزمایشی با `ZARINPAL_SANDBOX=true` در Staging (پرداخت موفق، انصراف، بستن مرورگر بدون بازگشت) و بررسی `payments:sweep` در Scheduler
- [ ] Tile نقشه از سرویس ایرانی (Proxy یا `map.tile_url`) و نمایش صحیح نام منبع روی نقشه
- [ ] Volume مربوط به `storage/app/public` (تصاویر کالا و آواتار) در پشتیبان‌گیری
- [ ] شبکه‌های تبلیغاتی خارجی فقط پس از بررسی مستند رسمی و با Secret فعال شوند
- [ ] پشتیبان و بازیابی تست شده
