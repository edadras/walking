# ۶. Security Model

## ۶.۱ تهدیدها و دفاع‌ها

| تهدید | دفاع |
|-------|------|
| ارسال مستقیم `steps=10000` با curl | هیچ Endpointی Reward نمی‌سازد؛ Session باید امضای کلید Keystore دستگاه ثبت‌شده را داشته باشد؛ Reward سمت سرور و پس از Fraud Engine |
| Replay یک Session معتبر | `UQ(device_id, client_session_id)`، `UQ(device_id, sequence)`، nonce یک‌بارمصرف در Redis (TTL ۱۰ دقیقه)، timestamp ±۵ دقیقه |
| دزدیدن Token | Token به device_id گره خورده؛ درخواست حساس بدون امضای کلید همان دستگاه رد می‌شود؛ Token روی Secure Storage |
| اپ دستکاری‌شده (Repackaged) | Play Integrity `appIntegrity`؛ Key attestation؛ Certificate pinning (Phase 9) |
| Emulator / Root | Play Integrity `deviceIntegrity` (سرور) + سیگنال‌های محلی کم‌وزن |
| Fake GPS | `isMock`/`isFromMockProvider`، Teleport، سازگاری سرعت و قدم، QR چرخشی |
| Brute-force OTP | ۵ تلاش per کد، Rate limit per phone/device/IP، HMAC ذخیره کد |
| Double spending | قفل ردیف Wallet، CHECK constraint، Idempotency-Key |
| Mass assignment / IDOR | Form Request whitelist، `public_id` به جای ID، Policyها |
| Admin abuse | نقش‌ها، Audit Log غیرقابل حذف، دلیل اجباری برای adjustment، 2FA برای Admin (Phase 9) |
| نشت داده | حداقل‌سازی، IP به‌صورت Hash، رمزنگاری ستون‌های حساس (`encrypted` cast)، مختصات Location کاهش دقت در لاگ |

## ۶.۲ Device Identity و Request Signing

1. در اولین اجرا اپ در **Android Keystore** یک جفت‌کلید `EC P-256` (غیرقابل Export، در صورت امکان StrongBox) می‌سازد.
2. `POST /devices/register` شامل `public_key` (SPKI/PEM) است و **خود درخواست با همان کلید امضا می‌شود** (اثبات مالکیت کلید). سرور `device.public_id` برمی‌گرداند.
3. هر درخواست حساس:

```
canonical = METHOD + "\n" + PATH + "\n" + X-Timestamp + "\n" + X-Nonce + "\n" + hex(SHA256(body))
X-Signature = base64( ECDSA_P256_SHA256(private_key, canonical) )   // DER
```

4. سرور (`VerifyDeviceSignature` middleware):
   - `X-Device-Id` وجود دارد و `status=active`؛ اگر کاربر لاگین است، `token.device_id == device.id`.
   - `|now − X-Timestamp| ≤ 300s` (clock skew به‌عنوان سیگنال Fraud هم ثبت می‌شود).
   - `SETNX nonce:{device}:{nonce} EX 600` → اگر وجود داشت: `nonce_reused`.
   - `openssl_verify(canonical, signature, public_key, SHA256)`.
5. کلید قابل Rotate است (`/devices/rotate-key` امضاشده با کلید قبلی) — Phase 9.

> چرا HMAC با Secret مشترک نه؟ Secret باید به Client داده شود و از حافظه قابل استخراج است. با ECDSA در Keystore، کلید خصوصی **هرگز در حافظه اپ نیست**.

## ۶.۳ Admin Roles

| نقش | دسترسی |
|-----|--------|
| `super_admin` | همه چیز، مدیریت Adminها، تنظیمات حساس (نرخ تبدیل، Feature Flag) |
| `operations` | کاربران (مشاهده، Suspend)، Challengeها، CMS، Announcement |
| `fraud_analyst` | Fraud Dashboard، تصمیم‌گیری Case، Ban، مشاهده Session و Device |
| `finance` | Wallet، Adjustment (با دلیل)، نرخ تبدیل (پیشنهاد)، گزارش‌های مالی |
| `store_manager` | محصولات، موجودی، سفارش‌ها، ارسال، Refund |
| `sponsor_manager` | تأیید Sponsor، Campaign، Location، Coupon، Ads |
| `support` | Ticketها، مشاهده محدود کاربر (بدون تغییر Point) |
| `content_editor` | CMS و FAQ |

Enforcement: Filament Resource `canViewAny/canEdit…` + Laravel Policy؛ همه Actionهای تغییر‌دهنده در `AuditLogger`.

## ۶.۴ Sponsor Roles

| نقش | دسترسی |
|-----|--------|
| `owner` | همه چیز در Sponsor خودش + مدیریت اعضا + Billing |
| `manager` | Campaign، Location، Coupon، Ads (ارسال برای تأیید) |
| `analyst` | فقط Analytics |
| `cashier` | نمایش Rotating QR شعبه و Redeem کوپن |

Tenant isolation: همه Query های Sponsor Panel با `sponsor_id = auth()->user()->sponsor_id` محدود می‌شوند (Filament Tenancy / Global scope).

## ۶.۵ Privacy

- Location: فقط در Session فعال (با رضایت)، Nearby Rewards و Visit. هرگز در پس‌زمینه دائم.
- Leaderboard فقط `display_name`، avatar و level؛ شماره تلفن هرگز در هیچ API عمومی نیست.
- Analytics بدون شماره تلفن، مختصات دقیق یا متن آزاد.
- حذف حساب: `users.status=deleted`، ناشناس‌سازی phone/display_name، حذف avatar، Profile و Tokenها؛ Ledger و Orderها برای الزامات مالی با `user_id` ناشناس‌شده باقی می‌مانند (این در متن «حریم خصوصی» به کاربر اعلام می‌شود).
- Retention: `activity_samples` ۳۰ روز، `ad_events` ۹۰ روز، `otp_codes` ۱ روز، `analytics_events` ۱۸۰ روز (پس از Aggregate).
