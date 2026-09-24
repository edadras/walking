# ۸. Design System و Visual Language

## ۸.۱ ایده بصری: «ردّ قدم» (Trail)

Motif اختصاصی محصول یک **مسیر نقطه‌چین** است: هر نقطه یک «قدم» است و پیشرفت یعنی پر شدن نقطه‌ها روی مسیر. این Motif جایگزین Illustrationهای ورزشی Stock می‌شود:

| جا | کاربرد Motif |
|----|--------------|
| Home — Step Progress | حلقه‌ای از ۶۰ نقطه کوچک (نه Arc پیوسته). نقطه‌های طی‌شده سبز، باقی‌مانده خاکستری روشن، نقطه «اکنون» کمی بزرگ‌تر. رسیدن به هدف: آخرین نقطه زرد می‌شود و یک پالس کوتاه |
| Loading | سه نقطه روی یک مسیر کوتاه که به ترتیب پر می‌شوند (به جای Spinner) |
| Empty State | مسیر نقطه‌چین که به یک نقطه توخالی ختم می‌شود + جمله راهنما |
| Achievement | مدال‌ها با حاشیه نقطه‌چین؛ باز شدن = پر شدن نقطه‌ها |
| Reward animation | نقطه زرد از محل Reward به سمت موجودی Wallet حرکت می‌کند و عدد Count-up می‌شود |
| Streak | ردیف ۷ نقطه روزهای هفته، نه آیکون آتش (آتش فقط در متن اختیاری) |

## ۸.۲ Tokens

### رنگ (Light)
| Token | مقدار | کاربرد |
|-------|-------|--------|
| `bg` | `#FFFFFF` | پس‌زمینه غالب |
| `surface` | `#F6F8F6` | کارت‌ها، بخش‌ها |
| `surfaceSunken` | `#EEF2EF` | Input، Skeleton |
| `border` | `#E3E8E4` | خطوط ۱px |
| `ink` | `#101814` | متن اصلی |
| `inkMuted` | `#56615B` | متن ثانویه |
| `inkSubtle` | `#8A948E` | برچسب، hint |
| `green` | `#1A7F4B` | فعالیت، سلامت، دکمه اصلی |
| `greenStrong` | `#0F5E36` | pressed |
| `greenSoft` | `#E7F3EC` | پس‌زمینه تأکید سبز |
| `gold` | `#E8A400` | Point، Reward (Accent — حداکثر ~۵٪ سطح صفحه) |
| `goldInk` | `#8A5D00` | متن روی goldSoft (کنتراست AA) |
| `goldSoft` | `#FFF4D1` | Badge امتیاز |
| `danger` | `#C3362B` | خطا |
| `dangerSoft` | `#FBECEA` | |
| `info` | `#2563A8` | |

### رنگ (Dark — معماری آماده، نسخه اول Light)
`bg #0C110E · surface #141B17 · surfaceSunken #1B241F · border #26312B · ink #EAF0EC · inkMuted #A3AFA8 · green #3FB57A · gold #F2B92E`

### Typography
فونت: **Vazirmatn** (OFL، طراحی‌شده برای فارسی، Variable) — Bundle در اپ (بدون وابستگی به شبکه). ارقام همیشه **فارسی** نمایش داده می‌شوند (`۶٬۸۴۰`) با جداکننده هزارگان `٬`.

| Style | Size/Line | Weight | کاربرد |
|-------|-----------|--------|--------|
| `display` | 44 / 1.1 | 800 | عدد قدم Home |
| `h1` | 24 / 1.4 | 700 | عنوان صفحه |
| `h2` | 19 / 1.45 | 700 | عنوان بخش |
| `title` | 16 / 1.5 | 600 | عنوان کارت |
| `body` | 14.5 / 1.75 | 400 | متن |
| `label` | 13 / 1.5 | 500 | دکمه، برچسب |
| `caption` | 12 / 1.5 | 400 | توضیح |
| `numeric` | — | 700 + `tabularFigures` | اعداد متغیر (Count-up بدون لرزش) |

### Spacing (پایه ۴)
`xxs 2 · xs 4 · sm 8 · md 12 · lg 16 · xl 20 · xxl 24 · x3 32 · x4 40 · x5 56` — Gutter صفحه: `20`.

### Radius (ظریف و کنترل‌شده)
`xs 4 (chip داخلی) · sm 8 (input، دکمه) · md 12 (کارت) · lg 16 (Bottom sheet)` — هیچ کارتی بیش از ۱۶.

### Elevation
به جای Shadow سنگین: **Border ۱px + تفاوت Surface**. تنها Shadow: Bottom Nav و Sheet با `0 -1 0 border` + blur 12 با opacity ۴٪.

### Motion
| Token | مقدار |
|-------|-------|
| `fast` | 120ms — Press state |
| `base` | 200ms — ورود/خروج المان |
| `slow` | 320ms — تغییر صفحه، Sheet |
| `count` | 700ms — Count-up اعداد |
| `curve` | `Curves.easeOutCubic`؛ خروج `easeInCubic` |

`MediaQuery.disableAnimations` رعایت می‌شود (Accessibility).

## ۸.۳ Components (در `core/widgets`)

- `AppButton` — primary (سبز)، secondary (Border)، ghost، `reward` (زرد، فقط برای Claim). ارتفاع ۴۸، حداقل Touch target ۴۸×۴۸.
- `AppCard` — Surface + Border ۱px + radius md؛ بدون Shadow.
- `AppTextField` — Label بالای فیلد، خطای فارسی زیر فیلد؛ ورودی شماره با ارقام لاتین داخلی و نمایش فارسی.
- `StatTile` — عدد + واحد + برچسب (قدم، km، kcal تخمینی، دقیقه).
- `PointsChip` — نقطه زرد + عدد + «امتیاز».
- `StepRing` — CustomPainter نقطه‌ای.
- `TrailLoader`، `Skeleton` (shimmer ملایم تک‌رنگ)، `EmptyState`، `ErrorState` (پیام فارسی + «تلاش دوباره»).
- `AppBottomNav` — ۵ آیتم، آیکون خطی ۲۲px، برچسب ۱۱px، آیتم فعال: آیکون پر + نقطه سبز کوچک زیر آن (نه Pill بزرگ).
- `PermissionPrimer` — Sheet توضیح «چرا» قبل از درخواست سیستم.
- آیکون‌ها: مجموعه خطی یکدست (Material Symbols Rounded outlined با weight سبک)؛ آیکون‌های اختصاصی (قدم، مسیر) با CustomPainter/SVG.

## ۸.۴ RTL و Accessibility

- `Directionality` از Locale (`fa` → RTL). فقط `EdgeInsetsDirectional`/`AlignmentDirectional` در Components (Lint rule داخلی).
- آیکون‌های جهت‌دار (فلش بازگشت، chevron) با `matchTextDirection: true`.
- Text scaling تا ۱.۳ بدون شکستن Layout؛ Semantics label فارسی برای StepRing («۶٬۸۴۰ قدم از ۱۰٬۰۰۰، ۶۸ درصد»).
- کنتراست متن حداقل AA (زرد هرگز رنگ متن روی سفید نیست؛ برای متن از `goldInk`).
