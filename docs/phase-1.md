# Phase 1 — Foundation

## خروجی

| بخش | وضعیت |
|-----|-------|
| Migrationها: `users`, `user_profiles`, `notification_preferences`, `account_deletion_requests`, `devices`, `device_user_links`, `personal_access_tokens(+device_id)`, `otp_codes`, `admins`, `settings`, `feature_flags`, `audit_logs`, `cms_pages`, `faqs` | ✅ |
| ثبت دستگاه با کلید Keystore و درخواست Self-signed | ✅ |
| Middleware امضا (ECDSA + Timestamp + Nonce یک‌بارمصرف) | ✅ |
| ورود OTP (Hash، Attempt limit، Rate limit per phone/device/IP، بدون User Enumeration) | ✅ |
| Token گره‌خورده به دستگاه؛ یک Session فعال per device | ✅ |
| Profile، تنظیمات، اعلان‌ها، حذف حساب (با مهلت انصراف)، مدیریت دستگاه‌ها | ✅ |
| `/config`: Feature Flag (rollout درصدی، حداقل نسخه، پلتفرم) + تنظیمات عمومی + اجبار به‌روزرسانی | ✅ |
| خطای Structured فارسی با `request_id` | ✅ |
| Audit Log غیرقابل ویرایش/حذف | ✅ |
| Admin Panel (Filament، RTL، Vazirmatn محلی): داشبورد، کاربران (Suspend/Ban/Activate با دلیل)، دستگاه‌ها، Feature Flagها، تنظیمات مرکزی، CMS، FAQ، مدیران، Audit | ✅ |
| Flutter: Design System، Network + امضا، Keystore channel (Kotlin)، Play Integrity، Router با Guard، Onboarding، ورود OTP، Shell و Bottom Nav اختصاصی، Home پایه، پروفایل و زیرصفحه‌ها، CMS | ✅ |

## تست‌ها

- Backend: ۴۸ تست Feature روی MySQL — ثبت دستگاه، امضا (کلید اشتباه، بدنه دستکاری‌شده، Timestamp کهنه، Replay Nonce)، OTP (Hash، تلاش‌ها، انقضا، مصرف دوباره، Throttle)، Token دزدیده‌شده روی دستگاه دیگر، دستگاه/کاربر مسدود، Profile، Config، Admin (نقش‌ها، تنظیمات با Audit، Ban).
- Flutter: ۲۰ تست — فرمت اعداد فارسی، اعتبارسنجی شماره، امضای دقیق بایت‌های ارسالی، یکتایی Nonce، بازیابی Clock skew، نگاشت خطا، RTL، StepRing و Accessibility، ErrorView بدون متن Exception خام، جریان ورود.

## تصمیم‌ها و انحراف‌ها

- **OTP پنج‌رقمی** با Attempt limit=۵ (قابل تنظیم).
- **Timezone** فقط هر ۷ روز یک بار قابل تغییر (ضد دوبار گرفتن Bonus روزانه).
- **Avatar پیش‌فرض پنل** به‌صورت SVG محلی؛ نام مدیران به سرویس خارجی ارسال نمی‌شود.
- **پشتیبان‌گیری Android** غیرفعال است: کلید Keystore و Token به دستگاه گره خورده‌اند و انتقال آن‌ها معنا ندارد.
- تب‌های فعالیت، جایزه‌ها و فروشگاه در Phaseهای ۲، ۳ و ۷ تکمیل می‌شوند.
