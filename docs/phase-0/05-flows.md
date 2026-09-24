# ۵. جریان‌های اصلی

## ۵.۱ Authentication Flow

```mermaid
sequenceDiagram
    participant A as App
    participant K as Android Keystore
    participant S as Server
    participant R as Redis
    participant SMS as SMS Provider

    Note over A: اولین اجرا
    A->>K: generateKeyPair(EC P-256, non-exportable)
    K-->>A: publicKey (+ attestation chain)
    A->>S: POST /devices/register {install_id, public_key, model, integrity_token}
    S->>S: verify self-signature با public_key · decode Play Integrity (server-side)
    S-->>A: {device_id}
    A->>A: ذخیره device_id در Secure Storage

    A->>S: POST /auth/otp/request {phone} + signature
    S->>R: rate limit (phone, device, ip)
    S->>S: code = random 5 رقم · ذخیره HMAC(code) · expires 2min
    S->>SMS: send(phone, code) [Queue]
    S-->>A: {expires_in, resend_in}

    A->>S: POST /auth/otp/verify {phone, code, referral_code?} + signature
    S->>S: attempts < 5 · hash_equals · not expired · consume
    S->>S: firstOrCreate user · link device↔user · (referral pending)
    S->>S: revoke توکن‌های قبلی همین دستگاه · issue Sanctum token(device_id)
    S-->>A: {token, user, is_new_user}
```

- OTP شش رقمی نه؛ **۵ رقمی** (تعادل UX و امنیت با Attempt Limit=۵ → احتمال حدس ۵/۱۰۰۰۰۰).
- کد OTP با `HMAC-SHA256(app.key, phone|code)` ذخیره می‌شود؛ دیتابیس دزدیده‌شده کد را لو نمی‌دهد.
- پاسخ `otp/request` برای شماره ثبت‌نشده و ثبت‌شده یکسان است (ضد User Enumeration).
- ساختار Auth با `AuthIdentity` قابل گسترش است؛ Email/Social در آینده یک Action جدید است، نه تغییر مدل Token.

## ۵.۲ Walking Verification Flow

```mermaid
sequenceDiagram
    participant SC as Step Counter (HW)
    participant W as WorkManager / FG Service
    participant Q as Local Signed Queue
    participant S as API
    participant F as Fraud Engine (Queue)
    participant RW as Reward Engine
    participant WL as WalletService

    SC-->>W: cumulative count since boot
    W->>W: delta · Step Detector events · accel stats · Activity Recognition
    W->>Q: Minute Buckets → Session (client_session_id, sequence)
    Q->>S: POST /walking-sessions (signed, nonce, timestamp)
    S->>S: validate schema · device active · token↔device · sequence > last · no overlap
    S->>S: store session(status=submitted, raw_steps) · buckets
    S-->>Q: 202 {session_id, status: submitted}
    S--)F: WalkingSessionSubmitted
    F->>F: run rules → confidence, risk, verified_steps, events
    F->>S: status = verified | partially_verified | under_review | rejected
    F--)RW: WalkingSessionScored
    RW->>RW: rules (rate, multipliers, caps) روی verified_steps
    RW->>WL: hold(user, points, idempotency=session:{id}) → pending
    Note over WL: پس از Hold Window و عدم Case باز → release → available
```

- پاسخ همگام (`202`) هیچ Point ای اعلام نمی‌کند؛ Home پس از چند ثانیه با Refresh یا Push نتیجه را نشان می‌دهد. در UI اپ «قدم‌های ثبت‌شده» بلافاصله نمایش داده می‌شوند (از سنسور محلی) و «امتیاز در حال بررسی» جدا.
- **Passive sessions**: پنجره‌های پس‌زمینه (حداکثر ۶۰ دقیقه) که از خوانش دوره‌ای Step Counter ساخته می‌شوند.
- **Active sessions**: کاربر «شروع پیاده‌روی» می‌زند؛ Foreground Service + GPS اختیاری با فاصله نمونه‌برداری تطبیقی.

## ۵.۳ Anti-Fraud Flow

Fraud Engine یک **Rule Engine وزن‌دار** است. هر Rule ورودی `SessionContext` (Session، Buckets، Device، سابقه کاربر، Sessionهای همپوشان) را می‌گیرد و `RuleResult` برمی‌گرداند:

```
RuleResult { rule_key, risk_points (0..100), step_cap (nullable), hard_reject (bool), evidence }
```

ترکیب نتایج:

```
fraud_risk      = min(100, Σ weight_i × risk_i / 100)          (hard_reject → 100)
verified_steps  = min(raw_steps, min(step_cap_i)) × plausibility_factor
confidence      = 100 − fraud_risk − penalties(no integrity, no detector data, …)
status:
  risk ≥ reject_threshold (80)   → rejected        (verified_steps = 0)
  risk ≥ review_threshold (50)   → under_review    (Case در Fraud Dashboard، Reward نگه داشته می‌شود)
  step_cap اعمال شد               → partially_verified
  otherwise                      → verified
```

### Ruleهای نسخه اول (همه با params قابل تنظیم در Admin)

| key | دسته | منطق | پیش‌فرض |
|-----|------|------|---------|
| `cadence_ceiling` | motion | Cadence پایدار بیش از حد انسانی | > ۲۲۰ قدم/دقیقه در ≥۳ دقیقه → cap آن دقیقه‌ها به ۱۸۰ |
| `impossible_rate` | motion | قدم در بازه کوتاه غیرممکن | > ۲۵۰ در یک دقیقه → حذف آن دقیقه |
| `detector_mismatch` | motion | اختلاف Step Counter و Step Detector | اختلاف > ۳۵٪ → risk ۳۰ |
| `motion_signature` | motion | واریانس شتاب ناسازگار با راه‌رفتن (تکان مصنوعی: فرکانس بالا/یکنواخت) | peak_hz خارج از ۱.۲–۳.۲Hz یا std خیلی یکنواخت → risk ۴۰ |
| `metronome_pattern` | motion | Cadence بیش از حد ثابت (دستگاه تکان‌دهنده) | CV cadence < ۲٪ در ≥۱۰ دقیقه → risk ۵۰ |
| `vehicle_speed` | gps | سرعت GPS خودرویی همراه قدم | > ۳.۵ m/s با cadence پایین یا activity=in_vehicle → cap |
| `teleport` | gps | پرش مکانی غیرممکن | سرعت ضمنی > ۵۰ m/s بین دو نقطه → risk ۶۰ |
| `mock_location` | device | Flag mock از Android | risk ۷۰ |
| `integrity_verdict` | device | Play Integrity | none → risk ۳۰، unavailable → risk ۱۰ + سقف روزانه |
| `emulator_root` | device | سیگنال Client | risk ۲۰ |
| `clock_skew` | time | فاصله زمان دستگاه و سرور، Session در آینده، Session خیلی قدیمی | skew > ۵ دقیقه → risk ۲۰؛ آینده → reject؛ > ۷ روز → reject |
| `overlapping_session` | time | همپوشانی زمانی با Session دیگر همین کاربر (هر دستگاهی) | reject بخش همپوشان |
| `timezone_hop` | time | تغییر timezone غیرمنطقی | risk ۳۰ |
| `daily_physiological_cap` | account | بیش از سقف روزانه انسانی | > ۶۰٬۰۰۰ قدم/روز → cap |
| `multi_account_device` | account | تعداد حساب روی یک دستگاه | ≥ ۳ حساب در ۳۰ روز → risk ۵۰ + review |
| `multi_device_account` | account | دستگاه‌های همزمان فعال | ≥ ۳ دستگاه فعال در ۲۴ ساعت → risk ۳۰ |
| `repeat_offender` | account | سابقه Sessionهای رد شده | ≥ ۳ رد در ۷ روز → risk ۴۰ |

- Replay و دستکاری API قبل از Fraud Engine در لایه Middleware رد می‌شوند (امضا، nonce، sequence).
- هر Rule فعال یک ردیف `fraud_events` می‌سازد (فقط اگر risk>0 یا cap).
- `FraudRule` یک Interface است؛ در آینده `MlScoringRule` که یک سرویس ML خارجی را صدا می‌زند، بدون تغییر Engine اضافه می‌شود.
- **Delayed analysis** (Scheduler، قبل از آزادسازی Pending): Multi-account clustering (دستگاه/IP مشترک)، الگوی Referral، Sessionهای یکسان بین حساب‌ها.

## ۵.۴ Reward Flow

```
input: session (verified_steps, started_at, local_date, user)
1. rate      = active step_rate rule            (مثال: 1000 steps = 10 points)
2. daily     = lock daily_activities(user, local_date) FOR UPDATE
3. allowed   = min(verified_steps, max_rewarded_steps − daily.rewarded_steps)
4. base      = floor(allowed × points / steps)       (باقیمانده قدم در روز منتقل می‌شود: محاسبه روی مجموع روز)
5. mult      = Π multipliers فعال در زمان شروع Session (weekend, bonus hour, campaign) — با سقف max_multiplier
6. points    = floor(base × mult)
7. points    = min(points, daily_cap − daily.points_earned, weekly_cap − week_points)
8. rewards row (breakdown JSON) · WalletService::hold(idempotency = "walking:{session_id}")
9. goal bonus: اگر daily.verified_steps از goal عبور کرد و goal_reached_at خالی است → hold("goal:{user}:{date}")
```

محاسبه base روی **مجموع روز** انجام می‌شود (`points_for(total_rewarded_steps) − points_already_from_steps`) تا گرد کردن در Sessionهای کوچک باعث از دست رفتن Point نشود.

## ۵.۵ Wallet Flow

```mermaid
stateDiagram-v2
    [*] --> pending: hold() — Reward صادر شد
    pending --> completed: release() — پس از Hold Window و بدون Case باز
    pending --> reversed: reverse() — Fraud تأیید شد
    completed --> [*]
    reversed --> [*]
```

هر عملیات در یک DB Transaction:

```php
DB::transaction(function () {
    $wallet = Wallet::whereKey($userId)->lockForUpdate()->first();
    // idempotency: اگر (user_id, key) وجود دارد → همان را برگردان
    // insert point_transactions (balance_before, balance_after)
    // update wallets
});
```

- **debit** (خرید): `available_balance >= amount` زیر قفل بررسی می‌شود؛ `CHECK` دیتابیس لایه دوم دفاع است.
- **adjustment** (Admin): فقط از طریق `WalletService::adjust(admin, amount, reason)`؛ reason اجباری؛ Audit Log.
- **expiration**: Scheduler با تراکنش `expiration` منفی (اگر سیاست انقضا فعال باشد — پیش‌فرض خاموش).
- ارزش ریالی: `available_balance × current_rate`. هر Transaction `rial_rate` لحظه خودش را دارد.

## ۵.۶ Sponsored Location Flow

```mermaid
sequenceDiagram
    participant A as App
    participant S as Server
    participant T as Sponsor Tablet (Rotating QR)

    A->>S: GET /locations/nearby (فقط هنگام باز بودن صفحه «جایزه‌های اطراف من»)
    A->>A: کاربر در محل، صفحه Campaign را باز می‌کند · Foreground location (بدون مجوز Background Location)
    A->>S: POST /visits {campaign, location, lat, lng, accuracy, mock_flag} (signed)
    S->>S: campaign active · ساعت کاری · distance ≤ radius + accuracy bound · limits (user/day/total/budget)
    S-->>A: visit(started) + min_stay
    loop هر 60 ثانیه تا min_stay
        A->>S: POST /visits/{id}/ping {lat, lng, accuracy}
        S->>S: هنوز داخل شعاع؟ teleport؟ stay_seconds += Δ(server time)
    end
    T->>T: token = base64(campaign|location|window|nonce|HMAC(location.qr_secret))
    A->>S: POST /visits/{id}/qr {token}
    S->>S: HMAC · window ±1 · location match · nonce unused (UQ)
    S->>S: verified → Reward (idempotency = visit:{id}) · coupon (اگر دارد)
    S-->>A: {status: rewarded, points, coupon}
```

- زمان حضور با **ساعت سرور** اندازه‌گیری می‌شود، نه ادعای Client.
- دقت GPS بد (accuracy > 100m) → درخواست تلاش مجدد، نه Reward.
- بودجه Sponsor به‌صورت اتمیک کسر می‌شود (`UPDATE campaigns SET points_spent = points_spent + ? WHERE points_spent + ? <= point_budget`).

## ۵.۷ Store Purchase Flow

```
POST /orders  {items, address_id?, payment_mode}  Idempotency-Key: <uuid>
1. Order موجود با همین (user, idempotency_key)؟ → همان را برگردان (200)
2. DB::transaction:
   a. products lockForUpdate (به ترتیب id برای جلوگیری از Deadlock)
   b. status=active · stock کافی · max_per_user
   c. total_points = Σ unit_point_price × qty (از DB، نه از Client)
   d. WalletService::debit(user, total_points, idempotency="order:{order_public_id}")
      → InsufficientPoints → rollback → 409
   e. stock -= qty (شرطی: WHERE stock >= qty)
   f. order(status=paid) · items(snapshot)
   g. دیجیتال: assign product_code (lockForUpdate, SKIP LOCKED) → delivered
3. afterCommit: OrderPlaced event → notification · analytics
```

- Point + Money: Order در `pending` با Hold کردن Point؛ پس از Callback موفق درگاه → `paid`. عدم پرداخت در ۱۵ دقیقه → آزادسازی Hold (Refund transaction).
- Cancel/Refund: تراکنش `refund` مثبت با idempotency `refund:{order}`؛ stock برمی‌گردد.
