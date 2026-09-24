# گام‌یار — Mobile (Flutter)

اپ Android (معماری آماده iOS)، فارسی و RTL. معماری: [`docs/phase-0/03-folder-structure.md`](../docs/phase-0/03-folder-structure.md).

## اجرا

```bash
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

| dart-define | توضیح |
|-------------|-------|
| `API_BASE_URL` | آدرس API (پیش‌فرض: Backend محلی از داخل Emulator) |
| `INTEGRITY_PROJECT_NUMBER` | شماره پروژه Google Cloud برای Play Integrity (۰ = غیرفعال) |

در Debug فقط به `10.0.2.2` و `localhost` اجازه HTTP داده می‌شود؛ Release فقط HTTPS.

## تست و کیفیت

```bash
flutter analyze
flutter test
flutter build apk --debug
```

## نکات معماری

- **State:** Riverpod 3 (بدون codegen). **Routing:** go_router با Guard بر اساس Session.
- **امنیت دستگاه:** کلید EC P-256 در Android Keystore (`android/.../DeviceKeyChannel.kt`). درخواست‌های حساس با `Req.signed()` امضا می‌شوند؛ کلید خصوصی هرگز وارد Dart نمی‌شود.
- **Localization:** فایل‌های ARB در `lib/l10n` (قالب: `app_fa.arb`)؛ کلاس‌ها با `flutter gen-l10n` تولید می‌شوند.
- **Design System:** تمام رنگ/فاصله/شعاع/حرکت از `lib/core/theme/tokens.dart`؛ Dark Mode در `AppPalette.dark` آماده است.
- **فونت:** Vazirmatn (SIL OFL) در `assets/fonts`.
