# ۴. API Architecture

## ۴.۱ قرارداد عمومی

- Base: `https://api.<domain>/api/v1`
- `Accept: application/json` · `Accept-Language: fa`
- احراز هویت: `Authorization: Bearer <token>` (Sanctum، گره‌خورده به دستگاه)
- شناسه دستگاه: `X-Device-Id: <device public_id>` در **همه** درخواست‌ها پس از ثبت دستگاه
- امضا (عملیات حساس — ستون «امضا» در جدول زیر): `X-Timestamp`، `X-Nonce`، `X-Signature` ([security.md](06-security.md))
- عملیات مالی: `Idempotency-Key: <uuid>`
- نسخه اپ: `X-App-Version` (برای Feature Flag و اجبار به‌روزرسانی)

### پاسخ موفق
```json
{ "data": { ... }, "meta": { "next_cursor": "..." } }
```

### پاسخ خطا (Structured)
```json
{
  "error": {
    "code": "otp_invalid",
    "message": "کد وارد شده صحیح نیست.",
    "fields": { "code": ["کد وارد شده صحیح نیست."] },
    "request_id": "01J..."
  }
}
```
`code` پایدار و ماشین‌خوان است (Client بر اساس آن رفتار می‌کند)؛ `message` فارسی و قابل نمایش. در Production هیچ Stack Trace یا پیام Exception داخلی برنمی‌گردد.

| HTTP | code نمونه |
|------|-----------|
| 401 | `unauthenticated`, `device_mismatch` |
| 403 | `account_suspended`, `device_blocked`, `feature_disabled`, `forbidden` |
| 409 | `duplicate_request`, `insufficient_points`, `out_of_stock` |
| 422 | `validation_failed`, `otp_invalid`, `otp_expired` |
| 426 | `upgrade_required` |
| 429 | `too_many_requests` |
| 400 | `signature_invalid`, `timestamp_skew`, `nonce_reused` |

### صفحه‌بندی
Cursor-based برای لیست‌های پرحجم (Ledger، Sessions، Notifications): `?cursor=…&per_page=20` (حداکثر ۵۰). Offset فقط برای لیست‌های کوچک Admin.

## ۴.۲ Endpoint Map

| Method | Path | Auth | امضا | Phase | توضیح |
|--------|------|------|------|-------|-------|
| **Bootstrap** |||||
| GET | `/config` | – | – | 1 | Feature Flagها، تنظیمات عمومی (هدف‌های پیشنهادی، نرخ تبدیل، حداقل نسخه) |
| **Device** |||||
| POST | `/devices/register` | – | self-signed | 1 | ثبت نصب + کلید عمومی + Integrity token |
| POST | `/devices/integrity` | ✓ | ✓ | 1 | تمدید Integrity verdict |
| PUT | `/devices/push-token` | ✓ | – | 1 | |
| **Auth** |||||
| POST | `/auth/otp/request` | – | ✓ | 1 | ارسال OTP (Rate limit per phone/device/IP) |
| POST | `/auth/otp/verify` | – | ✓ | 1 | صدور Token (+ کد معرف اختیاری) |
| POST | `/auth/logout` | ✓ | – | 1 | ابطال Token همین دستگاه |
| GET | `/auth/devices` | ✓ | – | 1 | دستگاه‌های کاربر |
| DELETE | `/auth/devices/{id}` | ✓ | ✓ | 1 | خروج از دستگاه دیگر |
| **Profile** |||||
| GET | `/me` | ✓ | – | 1 | پروفایل + آمار کلی |
| PATCH | `/me` | ✓ | – | 1 | نام، قد، وزن، سال تولد، timezone |
| POST | `/me/avatar` | ✓ | – | 1 | Multipart؛ روی سرور به JPEG مربعی ۵۱۲ پیکسلی Encode می‌شود (بدون EXIF) |
| DELETE | `/me/avatar` | ✓ | – | تکمیل | حذف عکس |
| PATCH | `/me/settings` | ✓ | – | 1 | هدف روزانه، هدف آب، Leaderboard visibility |
| GET/PATCH | `/me/notification-preferences` | ✓ | – | 1 | |
| POST | `/me/deletion-request` | ✓ | ✓ | 1 | درخواست حذف حساب |
| **Home** |||||
| GET | `/home` | ✓ | – | 1→4 | Aggregate: امروز، کیف پول، Streak، Challenge فعال، Placement تبلیغ |
| **Activity** |||||
| POST | `/walking-sessions` | ✓ | ✓ | 2 | ارسال Session + Minute Buckets (Idempotent با client_session_id) |
| POST | `/walking-sessions/batch` | ✓ | ✓ | 2 | Sync آفلاین (حداکثر ۲۰ Session) |
| GET | `/walking-sessions` | ✓ | – | 2 | |
| GET | `/walking-sessions/{id}` | ✓ | – | 2 | |
| GET | `/activity/day?date=` | ✓ | – | 2 | Timeline یک روز (پیش‌فرض امروز) + قدم ساعتی |
| GET | `/activity/daily?from&to` | ✓ | – | 2 | |
| GET | `/activity/weekly-report` | ✓ | – | 4 | |
| **Health** |||||
| GET | `/health/summary?range=week\|month` | ✓ | – | 4 | |
| GET/POST | `/health/water` | ✓ | – | 4 | |
| DELETE | `/health/water/{id}` | ✓ | – | 4 | |
| **Wallet & Rewards** |||||
| GET | `/wallet` | ✓ | – | 3 | موجودی‌ها + ارزش ریالی |
| GET | `/wallet/transactions?filter=` | ✓ | – | 3 | |
| GET | `/rewards` | ✓ | – | 3 | Reward Center (امروز، قابل دریافت، کمپین‌ها) |
| GET | `/rewards/{id}` | ✓ | – | 3 | Breakdown محاسبه |
| **Gamification** |||||
| GET | `/achievements` | ✓ | – | 4 | |
| GET | `/leaderboard?period=day\|week\|month` | ✓ | – | 4 | Top N + رتبه من |
| GET | `/challenges` · `/challenges/{id}` | ✓ | – | 4 | |
| POST | `/challenges/{id}/join` | ✓ | ✓ | 4 | |
| **Sponsors** |||||
| GET | `/locations/nearby?lat&lng&radius` | ✓ | – | 5 | مختصات با دقت کاهش‌یافته |
| GET | `/campaigns/{id}` | ✓ | – | 5 | |
| POST | `/visits` | ✓ | ✓ | 5 | شروع Visit در Geofence |
| POST | `/visits/{id}/ping` | ✓ | ✓ | 5 | حضور (Minimum Stay) |
| POST | `/visits/{id}/qr` | ✓ | ✓ | 5 | ارسال Token QR |
| GET | `/coupons` | ✓ | – | 5 | کوپن‌های من |
| POST | `/coupons/{id}/claim` | ✓ | ✓ | 5 | |
| **Ads** |||||
| GET | `/ads/placements/{key}` | ✓ | – | 6 | |
| POST | `/ads/events` | ✓ | – | 6 | impression/click (batch) |
| POST | `/ads/rewarded/start` | ✓ | ✓ | 6 | صدور view_token |
| POST | `/webhooks/ads/{provider}` | HMAC | – | 6 | S2S reward callback |
| **Store & Orders** |||||
| GET | `/store/categories` · `/store/products` · `/store/products/{slug}` | ✓ | – | 7 | |
| POST | `/orders` | ✓ | ✓ + Idempotency-Key | 7 | خرید Atomic |
| GET | `/orders` · `/orders/{id}` | ✓ | – | 7 | |
| GET/POST/PATCH/DELETE | `/addresses` | ✓ | – | 7 | |
| **Other** |||||
| GET | `/referral` | ✓ | – | 4 | کد و وضعیت دعوت‌ها |
| GET | `/notifications` · POST `/notifications/read` | ✓ | – | 4 | |
| GET/POST | `/support/tickets` · `/support/tickets/{id}/messages` | ✓ | – | 8 | |
| GET | `/pages/{slug}` · `/faqs` | – | – | 1 | CMS |
| POST | `/analytics/events` | ✓ | – | 2 | Batch، بدون PII |
| POST | `/client-errors` | – | – | تکمیل | گزارش کرش؛ عمومی، Throttle ۱۰/دقیقه، گروه‌بندی و پاک‌سازی PII |
| GET | `/map/tiles/{z}/{x}/{y}` | ✓ | – | پیشنهادها | Proxy و Cache برای Tile سرویس کلیددار؛ خارج از Throttle عمومی (۶۰۰ در دقیقه) |
| POST | `/streak/freezes` | ✓ | ✓ | پیشنهادها | خرید محافظ زنجیره؛ `Idempotency-Key` |
| GET | `/quests` | ✓ | – | پیشنهادها | مأموریت‌های روزانه/هفتگی با پیشرفت محاسبه‌شده در سرور |
| POST | `/quests/{key}/claim` | ✓ | ✓ | پیشنهادها | دریافت پاداش؛ یک‌بار در هر دوره |
| GET/POST | `/friends` | ✓ | – | پیشنهادها | فهرست و رتبه‌بندی هفتگی / درخواست دوستی با کد (Throttle `social`) |
| POST/DELETE | `/friends/{id}/accept`، `/friends/{id}` | ✓ | – | پیشنهادها | پذیرفتن / رد یا حذف |
| GET/POST | `/friend-challenges`، `/friend-challenges/{id}(/join,/leave)` | ✓ | – | پیشنهادها | رقابت دوستانه (بدون امتیاز) |
| – | `POST /orders` با `payment_mode: money` | ✓ | ✓ | پیشنهادها | سفارش ریالی؛ پاسخ شامل `payment.pay_url` (زرین‌پال) |
| GET | `/payments/zarinpal/callback` (وب) | – | – | پیشنهادها | بازگشت از بانک؛ تأیید سرور با مبلغ ذخیره‌شده |
| **Cash-out** (پرچم `cashout`) |||||
| GET | `/cashout` | ✓ | – | برداشت | وضعیت کامل: سقف‌ها، موانع (`blockers`)، هویت (کد ملی ماسک‌شده)، حساب‌ها، درخواست‌ها |
| POST | `/cashout/otp` | ✓ | ✓ | برداشت | کد پیامکی با هدف `cashout` به شماره خود حساب (کد ورود قبول نمی‌شود) |
| POST | `/cashout/identity` | ✓ | ✓ + `code` | برداشت | نام، نام خانوادگی (فارسی)، کد ملی (کنترل رقم)، تاریخ تولد (≥۱۸ سال) |
| POST/DELETE | `/cashout/bank-accounts`، `/cashout/bank-accounts/{id}` | ✓ | ✓ + `code` | برداشت | شبا (کنترل mod-97)، حداکثر ۳ حساب، یکتا بین کاربران |
| POST | `/cashout/requests` | ✓ | ✓ + `code` + Idempotency-Key | برداشت | کسر فوری امتیاز از دفتر کل؛ یک درخواست باز در هر زمان |
| POST | `/cashout/requests/{id}/cancel` | ✓ | ✓ | برداشت | فقط در وضعیت «در انتظار بررسی»؛ بازگشت امتیاز |

## ۴.۳ Rate Limitها (Redis)

| Limiter | مقدار پیش‌فرض |
|---------|---------------|
| `otp-request` | ۱ در ۶۰ ثانیه و ۵ در ساعت per phone؛ ۱۰ در ساعت per device؛ ۲۰ در ساعت per IP |
| `otp-verify` | ۵ تلاش per کد؛ ۱۰ در ساعت per phone |
| `api` | ۱۲۰ در دقیقه per user |
| `sessions` | ۳۰ در دقیقه per device |
| `purchase` | ۱۰ در دقیقه per user |
| `visits` | ۶۰ در دقیقه per user |
| `cashout` | ۱۰ در دقیقه و ۶۰ در روز per user؛ OTP برداشت جدا از ورود: ۵ در ساعت per phone |
