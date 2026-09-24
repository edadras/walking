# گام‌یار — Backend (Laravel 13)

REST API (`/api/v1`)، پنل مدیریت (`/admin`)، و در Phaseهای بعد پنل اسپانسر (`/sponsor`) و Workerها.
معماری و تصمیم‌ها: [`docs/`](../docs/README.md).

## پیش‌نیازها

- PHP 8.3+ (ext: `openssl`, `redis`, `pdo_mysql`, `intl`, `gd`)
- MySQL 8
- Redis 6+
- Composer 2

## راه‌اندازی محلی

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed          # PlatformSeeder + DemoSeeder (غیر production)
php artisan storage:link
php artisan serve
php artisan queue:work redis --queue=critical,default,fraud,notifications,analytics
```

پنل مدیریت: `http://localhost:8000/admin` — کاربر نمایشی: `admin@gamyar.test` / `password` (فقط DemoSeeder).
در Production مدیر ارشد با `ADMIN_EMAIL` و `ADMIN_PASSWORD` در اولین `db:seed --class=PlatformSeeder` ساخته می‌شود.

## تست‌ها

تست‌ها روی **MySQL** اجرا می‌شوند (قفل ردیف و Constraintها باید واقعی باشند):

```sql
CREATE DATABASE walking_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan test
./vendor/bin/pint --test
```

## متغیرهای محیطی مهم

| متغیر | توضیح |
|-------|-------|
| `SMS_DRIVER` | `log` (فقط توسعه؛ در production خطا می‌دهد) یا `kavenegar` |
| `KAVENEGAR_API_KEY`, `KAVENEGAR_OTP_TEMPLATE` | ارسال OTP با قالب Verify |
| `INTEGRITY_DRIVER` | `null` یا `google` |
| `PLAY_INTEGRITY_CREDENTIALS` | مسیر فایل JSON سرویس‌اکانت Google |
| `ANDROID_PACKAGE_NAME` | باید با `applicationId` اپ یکی باشد |
| `TRUSTED_PROXIES` | IP لود‌بالانسر؛ خالی = هیچ Proxy مورد اعتماد نیست |

## ساختار

- `app/Domain/*` — منطق کسب‌وکار (Service/Action). Controllerها نازک‌اند.
- `app/Http/Middleware/VerifyDeviceSignature.php` — امضای ECDSA، Nonce و Timestamp.
- `app/Filament/Admin` — پنل مدیریت (دسترسی بر اساس `AdminRole::abilities()`).
- `config/walk.php` — مقادیر پیش‌فرض تنظیمات و Feature Flagها (قابل تغییر از پنل).
