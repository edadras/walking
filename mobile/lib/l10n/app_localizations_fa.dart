// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Persian (`fa`).
class AppLocalizationsFa extends AppLocalizations {
  AppLocalizationsFa([String locale = 'fa']) : super(locale);

  @override
  String get appName => 'گام‌یار';

  @override
  String get commonRetry => 'تلاش دوباره';

  @override
  String get commonLoading => 'در حال بارگذاری';

  @override
  String get commonSave => 'ذخیره';

  @override
  String get commonCancel => 'انصراف';

  @override
  String get commonConfirm => 'تأیید';

  @override
  String get commonContinue => 'ادامه';

  @override
  String get commonSkip => 'رد شدن';

  @override
  String get commonClose => 'بستن';

  @override
  String get commonSaved => 'تغییرات ذخیره شد.';

  @override
  String get commonSoon => 'به‌زودی';

  @override
  String get navHome => 'خانه';

  @override
  String get navActivity => 'فعالیت';

  @override
  String get navRewards => 'جایزه‌ها';

  @override
  String get navStore => 'فروشگاه';

  @override
  String get navProfile => 'پروفایل';

  @override
  String get onboardingMoveTitle => 'حرکت کن';

  @override
  String get onboardingMoveBody =>
      'مثل همیشه راه برو. گام‌یار قدم‌هایت را در پس‌زمینه و با کمترین مصرف باتری ثبت می‌کند.';

  @override
  String get onboardingEarnTitle => 'امتیاز بگیر';

  @override
  String get onboardingEarnBody =>
      'هر قدم تأییدشده به امتیاز تبدیل می‌شود؛ ارزش ریالی امتیازت را همیشه می‌بینی.';

  @override
  String get onboardingRewardTitle => 'جایزه دریافت کن';

  @override
  String get onboardingRewardBody =>
      'با رسیدن به هدف روزانه، زنجیره روزها و چالش‌ها جایزه بیشتری بگیر.';

  @override
  String get onboardingStoreTitle => 'از فروشگاه خرید کن';

  @override
  String get onboardingStoreBody => 'با امتیازهایت کارت هدیه، کوپن و کالا بخر.';

  @override
  String get onboardingNearbyTitle => 'جایزه‌های اطرافت را پیدا کن';

  @override
  String get onboardingNearbyBody =>
      'به فروشگاه‌های همکار سر بزن و جایزه ویژه بگیر.';

  @override
  String get onboardingStart => 'شروع کنیم';

  @override
  String get authPhoneTitle => 'ورود به گام‌یار';

  @override
  String get authPhoneSubtitle =>
      'شماره موبایلت را وارد کن تا کد تأیید برایت پیامک شود.';

  @override
  String get authPhoneLabel => 'شماره موبایل';

  @override
  String get authPhoneHint => '۰۹۱۲ ۳۴۵ ۶۷۸۹';

  @override
  String get authPhoneInvalid => 'شماره موبایل معتبر نیست.';

  @override
  String get authSendCode => 'دریافت کد';

  @override
  String get authTermsNote =>
      'ورود به معنی پذیرش قوانین و سیاست حریم خصوصی گام‌یار است.';

  @override
  String get authTermsLink => 'قوانین و حریم خصوصی';

  @override
  String get authOtpTitle => 'کد تأیید';

  @override
  String authOtpSubtitle(String phone) {
    return 'کد ارسال‌شده به $phone را وارد کن.';
  }

  @override
  String get authOtpLabel => 'کد تأیید';

  @override
  String get authEditPhone => 'ویرایش شماره';

  @override
  String get authResend => 'ارسال دوباره کد';

  @override
  String authResendIn(String seconds) {
    return 'ارسال دوباره تا $seconds ثانیه دیگر';
  }

  @override
  String get authReferralToggle => 'کد معرف دارم';

  @override
  String get authReferralLabel => 'کد معرف (اختیاری)';

  @override
  String get authVerify => 'تأیید و ورود';

  @override
  String get authPreparingDevice => 'آماده‌سازی امن دستگاه…';

  @override
  String homeGreeting(String name) {
    return 'سلام $name';
  }

  @override
  String homeToday(String date) {
    return 'امروز، $date';
  }

  @override
  String homeRemaining(String steps) {
    return '$steps قدم تا هدف امروز';
  }

  @override
  String get homeGoalReached => 'هدف امروز را کامل کردی';

  @override
  String get homeDistance => 'مسافت';

  @override
  String get homeCalories => 'کالری تخمینی';

  @override
  String get homeActiveTime => 'زمان فعالیت';

  @override
  String get homeUnitKm => 'کیلومتر';

  @override
  String get homeUnitKcal => 'کیلوکالری';

  @override
  String get homeUnitMin => 'دقیقه';

  @override
  String get homePointsToday => 'امتیاز امروز';

  @override
  String get homePointsValue => 'ارزش تقریبی';

  @override
  String homeStreak(String days) {
    return '$days روز متوالی';
  }

  @override
  String get homeNoStreak => 'امروز اولین روز زنجیره‌ات را بساز';

  @override
  String get profileTitle => 'پروفایل';

  @override
  String profileLevel(String level) {
    return 'سطح $level';
  }

  @override
  String profileJoined(String date) {
    return 'عضو از $date';
  }

  @override
  String get profileEdit => 'ویرایش پروفایل';

  @override
  String get profileSectionActivity => 'فعالیت';

  @override
  String get profileDailyGoal => 'هدف روزانه';

  @override
  String get profileWaterGoal => 'هدف مصرف آب';

  @override
  String get profileSectionPrivacy => 'حریم خصوصی و امنیت';

  @override
  String get profileLeaderboardVisible => 'نمایش من در رتبه‌بندی';

  @override
  String get profileLeaderboardVisibleHint =>
      'فقط نام نمایشی، تصویر و سطح نمایش داده می‌شود.';

  @override
  String get profileNotifications => 'اعلان‌ها';

  @override
  String get profileDevices => 'دستگاه‌های من';

  @override
  String get profileDeleteAccount => 'حذف حساب کاربری';

  @override
  String get profileSectionAbout => 'درباره';

  @override
  String get profileHowToEarn => 'نحوه دریافت امتیاز';

  @override
  String get profileRewardRules => 'قوانین پاداش';

  @override
  String get profileTerms => 'قوانین و مقررات';

  @override
  String get profilePrivacy => 'حریم خصوصی';

  @override
  String get profileFaq => 'سوالات متداول';

  @override
  String get profileAbout => 'درباره گام‌یار';

  @override
  String get profileLogout => 'خروج از حساب';

  @override
  String get profileLogoutConfirm => 'از حساب کاربری خارج می‌شوی؟';

  @override
  String profileVersion(String version) {
    return 'نسخه $version';
  }

  @override
  String get editProfileTitle => 'ویرایش پروفایل';

  @override
  String get editDisplayName => 'نام نمایشی';

  @override
  String get editDisplayNameHint => 'مثلاً سارا';

  @override
  String get editBirthYear => 'سال تولد (میلادی)';

  @override
  String get editHeight => 'قد (سانتی‌متر)';

  @override
  String get editWeight => 'وزن (کیلوگرم)';

  @override
  String get editGender => 'جنسیت (اختیاری)';

  @override
  String get editGenderFemale => 'زن';

  @override
  String get editGenderMale => 'مرد';

  @override
  String get editGenderNone => 'ترجیح می‌دهم نگویم';

  @override
  String get editBodyNote =>
      'قد، وزن و سن فقط برای تخمین کالری و مسافت استفاده می‌شوند و به هیچ‌کس نمایش داده نمی‌شوند.';

  @override
  String get goalTitle => 'هدف روزانه';

  @override
  String get goalSubtitle =>
      'هدفی انتخاب کن که هر روز بتوانی به آن برسی. بعداً هم قابل تغییر است.';

  @override
  String get goalCustom => 'مقدار دلخواه';

  @override
  String goalSteps(String steps) {
    return '$steps قدم';
  }

  @override
  String goalRange(String min, String max) {
    return 'بین $min و $max قدم';
  }

  @override
  String get waterGoalTitle => 'هدف مصرف آب';

  @override
  String get waterGoalNote => 'این عدد تقریبی است و توصیه پزشکی نیست.';

  @override
  String waterGlasses(String glasses) {
    return '$glasses لیوان';
  }

  @override
  String get notificationsTitle => 'اعلان‌ها';

  @override
  String get notificationsMandatory =>
      'اعلان وضعیت سفارش برای اطلاع از خریدهایت همیشه فعال است.';

  @override
  String get devicesTitle => 'دستگاه‌های من';

  @override
  String get devicesCurrent => 'همین دستگاه';

  @override
  String devicesLastSeen(String time) {
    return 'آخرین استفاده: $time';
  }

  @override
  String get devicesRevoke => 'خروج';

  @override
  String get devicesRevokeConfirm => 'این دستگاه از حساب شما خارج شود؟';

  @override
  String get devicesEmpty => 'دستگاه دیگری به حساب شما متصل نیست.';

  @override
  String get deleteAccountTitle => 'حذف حساب کاربری';

  @override
  String deleteAccountBody(String days) {
    return 'با درخواست حذف، حساب شما پس از $days روز حذف می‌شود و تا آن زمان می‌توانید انصراف دهید. اطلاعات شخصی حذف و سوابق مالی به‌صورت ناشناس نگهداری می‌شوند.';
  }

  @override
  String get deleteAccountConfirm => 'درخواست حذف حساب';

  @override
  String deleteAccountPending(String date) {
    return 'حساب شما در تاریخ $date حذف می‌شود.';
  }

  @override
  String get deleteAccountCancel => 'انصراف از حذف حساب';

  @override
  String get comingNextTitle => 'این بخش در حال آماده‌سازی است';

  @override
  String get comingNextBody => 'به‌زودی از همین‌جا در دسترس خواهد بود.';

  @override
  String get updateRequiredTitle => 'نسخه جدید لازم است';

  @override
  String get updateRequiredBody => 'برای ادامه، گام‌یار را به‌روزرسانی کنید.';

  @override
  String get accountBlockedTitle => 'دسترسی محدود شده است';

  @override
  String get accountBlockedContact => 'برای پیگیری با پشتیبانی تماس بگیرید.';

  @override
  String get permAllow => 'اجازه می‌دهم';

  @override
  String get permNotNow => 'فعلاً نه';

  @override
  String get permOpenSettings => 'رفتن به تنظیمات';

  @override
  String get permOpenSettingsHint =>
      'این دسترسی قبلاً رد شده است. برای فعال‌سازی، از تنظیمات گوشی آن را روشن کنید.';

  @override
  String get permActivityTitle => 'ثبت خودکار قدم‌ها';

  @override
  String get permActivityBody =>
      'گام‌یار برای شمردن قدم‌هایت به «فعالیت بدنی» دسترسی لازم دارد. این کار با شمارنده قدم خود گوشی و با کمترین مصرف باتری انجام می‌شود؛ موقعیت مکانی تو ثبت نمی‌شود.';

  @override
  String get permNotificationsTitle => 'اعلان پیاده‌روی';

  @override
  String get permNotificationsBody =>
      'وقتی پیاده‌روی را شروع می‌کنی، یک اعلان ثابت نشان می‌دهد که ثبت قدم فعال است و تعداد قدم‌ها را می‌بینی.';

  @override
  String get permLocationTitle => 'ثبت مسیر پیاده‌روی';

  @override
  String get permLocationBody =>
      'برای محاسبه دقیق‌تر مسافت، موقعیت مکانی فقط در همین پیاده‌روی و فقط وقتی خودت آن را فعال کنی استفاده می‌شود. مسیر کامل تو به سرور ارسال نمی‌شود.';

  @override
  String get trackingOffTitle => 'ثبت خودکار قدم‌ها خاموش است';

  @override
  String get trackingOffBody =>
      'اجازه دسترسی به فعالیت بدنی را بده تا قدم‌هایت بدون باز بودن برنامه ثبت شوند.';

  @override
  String get trackingEnable => 'فعال‌سازی';

  @override
  String get trackingNoSensor =>
      'این گوشی شمارنده قدم ندارد؛ ثبت خودکار قدم امکان‌پذیر نیست.';

  @override
  String homeAwaiting(String steps) {
    return '$steps قدم در حال بررسی';
  }

  @override
  String get homeStartWalk => 'شروع پیاده‌روی';

  @override
  String get homeWalkInProgress => 'پیاده‌روی در حال ثبت';

  @override
  String get homeThisWeek => 'این هفته';

  @override
  String homeSyncedAt(String time) {
    return 'به‌روزرسانی $time';
  }

  @override
  String get activityTitle => 'فعالیت';

  @override
  String get activityToday => 'امروز';

  @override
  String get activityHourly => 'قدم‌ها در طول روز';

  @override
  String get activityTimeline => 'زمان‌بندی';

  @override
  String get activityEmptyTitle => 'هنوز قدمی ثبت نشده';

  @override
  String get activityEmptyBody =>
      'کمی راه برو؛ قدم‌هایت اینجا نمایش داده می‌شوند.';

  @override
  String get activitySteps => 'قدم';

  @override
  String get activityPassive => 'ثبت خودکار';

  @override
  String get activityActive => 'پیاده‌روی';

  @override
  String get sessionTitle => 'جزئیات فعالیت';

  @override
  String get sessionDuration => 'مدت';

  @override
  String get sessionPerMinute => 'قدم در هر دقیقه';

  @override
  String get sessionVerified => 'قدم تأییدشده';

  @override
  String get sessionSamplesExpired =>
      'جزئیات دقیقه‌ای فقط تا ۳۰ روز نگهداری می‌شوند.';

  @override
  String get walkTitle => 'پیاده‌روی';

  @override
  String get walkIntro =>
      'پیاده‌روی را شروع کن تا قدم‌ها، زمان و مسافتت دقیق‌تر ثبت شود. می‌توانی برنامه را ببندی؛ ثبت ادامه دارد.';

  @override
  String get walkGpsToggle => 'ثبت مسیر با GPS';

  @override
  String get walkGpsHint => 'مسافت دقیق‌تر، مصرف باتری بیشتر';

  @override
  String get walkStart => 'شروع';

  @override
  String get walkStop => 'پایان پیاده‌روی';

  @override
  String get walkSaving => 'در حال ذخیره…';

  @override
  String get walkElapsed => 'زمان';

  @override
  String get walkDistance => 'مسافت';

  @override
  String get walkDoneTitle => 'آفرین! پیاده‌روی ثبت شد';

  @override
  String walkDoneBody(String steps, String minutes) {
    return '$steps قدم در $minutes دقیقه. امتیاز پس از بررسی به حسابت اضافه می‌شود.';
  }

  @override
  String get walkDoneOffline =>
      'اینترنت در دسترس نیست؛ پیاده‌روی ذخیره شد و پس از اتصال ارسال می‌شود.';

  @override
  String get walkDone => 'باشه';

  @override
  String get walkNoSensor => 'این گوشی شمارنده قدم ندارد.';

  @override
  String get walkNeedsPermission =>
      'برای شروع پیاده‌روی، دسترسی فعالیت بدنی لازم است.';

  @override
  String get walkFailed => 'شروع پیاده‌روی ممکن نشد. دوباره تلاش کن.';

  @override
  String get pointsUnit => 'امتیاز';

  @override
  String pointsPlus(String points) {
    return '+$points امتیاز';
  }

  @override
  String get homePointsCard => 'امتیاز امروز';

  @override
  String get homeWalletLink => 'کیف پول';

  @override
  String get walletTitle => 'کیف پول';

  @override
  String get walletAvailable => 'امتیاز قابل استفاده';

  @override
  String walletValue(String value) {
    return 'ارزش تقریبی: $value';
  }

  @override
  String get walletPending => 'در حال بررسی';

  @override
  String get walletPendingHint =>
      'امتیازهای جدید پس از بررسی امنیتی قابل استفاده می‌شوند.';

  @override
  String walletNextRelease(String time) {
    return 'آزادسازی بعدی: $time';
  }

  @override
  String get walletLifetime => 'کل دریافتی';

  @override
  String get walletSpent => 'کل مصرف';

  @override
  String walletRate(String rial) {
    return 'هر امتیاز ≈ $rial';
  }

  @override
  String get walletHistory => 'تاریخچه';

  @override
  String get walletFilterAll => 'همه';

  @override
  String get walletFilterEarned => 'دریافتی';

  @override
  String get walletFilterSpent => 'مصرف';

  @override
  String get walletFilterPurchase => 'خرید';

  @override
  String get walletFilterReward => 'جایزه';

  @override
  String get walletFilterSponsor => 'اسپانسر';

  @override
  String get walletFilterAdjustment => 'اصلاح';

  @override
  String get walletEmpty => 'هنوز تراکنشی نداری';

  @override
  String get walletEmptyBody => 'با راه رفتن اولین امتیازت را بگیر.';

  @override
  String get walletReversed => 'لغو شد';

  @override
  String get rewardsTitle => 'جایزه‌ها';

  @override
  String get rewardsToday => 'امتیاز امروز';

  @override
  String rewardsTodayOf(String cap) {
    return 'از سقف $cap امتیاز روزانه';
  }

  @override
  String get rewardsHowTitle => 'چطور امتیاز بیشتری بگیری؟';

  @override
  String rewardsRate(String steps) {
    return 'هر $steps قدم تأییدشده';
  }

  @override
  String rewardsRemaining(String steps) {
    return 'امروز تا $steps قدم دیگر امتیاز دارد';
  }

  @override
  String get rewardsGoalBonus => 'رسیدن به هدف روزانه';

  @override
  String get rewardsGoalDone => 'دریافت شد';

  @override
  String rewardsStreak(String days) {
    return '$days روز متوالی';
  }

  @override
  String rewardsMultiplierNow(String factor) {
    return 'الان ضریب ×$factor فعال است';
  }

  @override
  String get rewardsUpcoming => 'روزهای ویژه';

  @override
  String get rewardsRecent => 'آخرین جایزه‌ها';

  @override
  String get rewardKindWalking => 'پاداش قدم';

  @override
  String get rewardKindGoal => 'پاداش هدف روزانه';

  @override
  String get rewardKindStreak => 'پاداش روزهای متوالی';

  @override
  String get rewardKindOther => 'جایزه';

  @override
  String get rewardPending => 'در حال بررسی';

  @override
  String get rewardDenied => 'سقف روزانه';

  @override
  String get rewardReversed => 'لغوشده';

  @override
  String get sessionConfidence => 'اطمینان';

  @override
  String get sessionReward => 'امتیاز این فعالیت';

  @override
  String get healthTitle => 'سلامت و آمار';

  @override
  String get healthWeek => '۷ روز اخیر';

  @override
  String get healthMonth => '۳۰ روز اخیر';

  @override
  String get healthDisclaimer =>
      'این آمار برای انگیزه و پیگیری فعالیت است و جنبه تشخیص یا توصیه پزشکی ندارد.';

  @override
  String get healthAvgDaily => 'میانگین روزانه';

  @override
  String get healthAvgWeekly => 'میانگین هفتگی';

  @override
  String get healthAvgMonthly => 'میانگین ماهانه';

  @override
  String get healthTotal => 'مجموع';

  @override
  String healthGoalDays(String n) {
    return '$n روز هدف کامل';
  }

  @override
  String get healthRecords => 'رکوردهای شخصی';

  @override
  String get recordBestDay => 'بهترین روز';

  @override
  String get recordBestWeek => 'بهترین هفته';

  @override
  String get recordBestSession => 'بهترین پیاده‌روی';

  @override
  String get healthStreak => 'زنجیره روزها';

  @override
  String healthStreakValue(String current, String longest) {
    return '$current روز · بهترین: $longest';
  }

  @override
  String get weeklyTitle => 'گزارش هفتگی';

  @override
  String get weeklyThisWeek => 'این هفته';

  @override
  String weeklyGoalDays(String done, String total) {
    return '$done از $total روز هدف تکمیل شده';
  }

  @override
  String weeklyChangeUp(String percent) {
    return '$percent بیشتر از هفته قبل';
  }

  @override
  String weeklyChangeDown(String percent) {
    return '$percent کمتر از هفته قبل';
  }

  @override
  String get weeklyNoCompare => 'برای مقایسه، داده هفته قبل وجود ندارد.';

  @override
  String get waterTitle => 'مصرف آب';

  @override
  String waterGlassesOf(String n, String goal) {
    return '$n از $goal لیوان';
  }

  @override
  String waterAddMl(String ml) {
    return '+$ml میلی‌لیتر';
  }

  @override
  String waterSuggested(String ml) {
    return 'پیشنهاد تقریبی برای تو: $ml میلی‌لیتر در روز';
  }

  @override
  String get waterUseSuggestion => 'تنظیم به‌عنوان هدف';

  @override
  String get waterReminder => 'یادآوری آب';

  @override
  String waterReminderEvery(String minutes) {
    return 'هر $minutes دقیقه، از ۹ صبح تا ۹ شب';
  }

  @override
  String get waterTodayLogs => 'ثبت‌های امروز';

  @override
  String get waterEmpty => 'امروز هنوز آبی ثبت نکرده‌ای.';

  @override
  String homeStreak7(String days) {
    return '$days روز متوالی';
  }

  @override
  String get homeWater => 'آب امروز';

  @override
  String get homeChallenge => 'چالش فعال';

  @override
  String get homeNotifications => 'اعلان‌ها';

  @override
  String levelLabel(String level, String title) {
    return 'سطح $level · $title';
  }

  @override
  String levelXp(String xp, String next) {
    return '$xp از $next XP';
  }

  @override
  String get levelMax => 'بالاترین سطح';

  @override
  String get achievementsTitle => 'دستاوردها';

  @override
  String achievementsCount(String n, String total) {
    return '$n از $total دستاورد';
  }

  @override
  String get leaderboardTitle => 'رتبه‌بندی';

  @override
  String get lbToday => 'امروز';

  @override
  String get lbWeek => 'این هفته';

  @override
  String get lbMonth => 'این ماه';

  @override
  String get lbYou => 'شما';

  @override
  String get lbHidden =>
      'نمایش شما در رتبه‌بندی خاموش است. دیگران نام و رتبه تو را نمی‌بینند.';

  @override
  String get lbEmpty => 'هنوز کسی در این دوره رتبه ندارد.';

  @override
  String get lbPrivacy =>
      'فقط نام نمایشی، تصویر و سطح نشان داده می‌شود. رتبه‌بندی بر اساس قدم تأییدشده است.';

  @override
  String get referralTitle => 'دعوت از دوستان';

  @override
  String referralBody(String steps, String mine, String theirs) {
    return 'کد دعوتت را برای دوستانت بفرست. وقتی دوستت $steps قدم تأییدشده بردارد، تو $mine و او $theirs امتیاز می‌گیرید.';
  }

  @override
  String get referralShare => 'اشتراک‌گذاری';

  @override
  String get referralCopied => 'کد دعوت کپی شد.';

  @override
  String get referralInvited => 'دعوت‌شده';

  @override
  String get referralActive => 'فعال‌شده';

  @override
  String get referralPoints => 'امتیاز دعوت';

  @override
  String referralShareText(String code) {
    return 'با گام‌یار راه برو و جایزه بگیر! هنگام ثبت‌نام کد دعوت من را وارد کن: $code';
  }

  @override
  String get challengesTitle => 'چالش‌ها';

  @override
  String get challengesRunning => 'در جریان';

  @override
  String get challengesUpcoming => 'به‌زودی';

  @override
  String get challengesEnded => 'پایان‌یافته';

  @override
  String get challengeJoin => 'شرکت در چالش';

  @override
  String get challengeJoined => 'در حال انجام';

  @override
  String get challengeCompleted => 'تکمیل شد';

  @override
  String get challengeReward => 'جایزه';

  @override
  String challengeParticipants(String n) {
    return '$n شرکت‌کننده';
  }

  @override
  String challengeEnds(String time) {
    return 'پایان: $time';
  }

  @override
  String get challengeTop => 'پیشتازها';

  @override
  String get challengeSponsored => 'اسپانسری';

  @override
  String get challengesEmpty => 'فعلاً چالشی فعال نیست.';

  @override
  String challengeTarget(String target) {
    return 'هدف: $target';
  }

  @override
  String get challengeProgressOnlyAfterJoin =>
      'فقط فعالیت‌های تأییدشده پس از پیوستن شمرده می‌شوند.';

  @override
  String get inboxTitle => 'اعلان‌ها';

  @override
  String get inboxEmpty => 'اعلانی نداری.';

  @override
  String get inboxMarkRead => 'خواندن همه';

  @override
  String get profileAchievements => 'دستاوردها';

  @override
  String get profileReferral => 'دعوت از دوستان';

  @override
  String get profileHealth => 'سلامت و آمار';

  @override
  String get profileLeaderboard => 'رتبه‌بندی';

  @override
  String get profileWater => 'مصرف آب';

  @override
  String get unitSteps => 'قدم';

  @override
  String get unitKm => 'کیلومتر';

  @override
  String get nearbyTitle => 'جایزه‌های اطراف';

  @override
  String get nearbyList => 'فهرست';

  @override
  String get nearbyMap => 'نقشه';

  @override
  String get nearbyEmpty =>
      'فعلاً در این اطراف پیشنهادی نیست. کمی جابه‌جا شو یا بعداً سر بزن.';

  @override
  String get nearbyLocationOff => 'موقعیت مکانی (GPS) گوشی خاموش است.';

  @override
  String get nearbyLocationDenied =>
      'برای دیدن جایزه‌های اطراف، اجازه دسترسی به موقعیت لازم است.';

  @override
  String get nearbyEnableLocation => 'فعال کردن موقعیت';

  @override
  String get nearbyPrivacy =>
      'موقعیتت فقط برای همین جستجو استفاده می‌شود و ذخیره نمی‌شود.';

  @override
  String distanceM(String m) {
    return '$m متر';
  }

  @override
  String distanceKm(String km) {
    return '$km کیلومتر';
  }

  @override
  String get branchClosed => 'الان بسته است';

  @override
  String campaignStay(String min) {
    return '$min دقیقه حضور';
  }

  @override
  String get campaignQr => 'اسکن QR صندوق';

  @override
  String campaignCoupon(String title) {
    return 'کوپن هدیه: $title';
  }

  @override
  String get campaignHow => 'چطور جایزه بگیرم؟';

  @override
  String get campaignStep1 => 'به یکی از شعبه‌های زیر برو.';

  @override
  String campaignStep2(String min) {
    return '«شروع بازدید» را بزن و $min دقیقه در شعبه بمان.';
  }

  @override
  String get campaignStep3 => 'QR روی صفحه صندوق را اسکن کن.';

  @override
  String get campaignStart => 'شروع بازدید';

  @override
  String get campaignBranches => 'شعبه‌ها';

  @override
  String campaignMine(String n, String max) {
    return '$n از $max پاداش گرفته‌ای';
  }

  @override
  String get reason_limit_reached => 'پاداش این کمپین را گرفته‌ای.';

  @override
  String get reason_cooldown => 'به‌تازگی از این شعبه پاداش گرفته‌ای.';

  @override
  String get reason_campaign_exhausted => 'ظرفیت این کمپین تمام شده است.';

  @override
  String get reason_campaign_ended => 'این کمپین تمام شده است.';

  @override
  String get reason_teleport => 'موقعیتت به‌شکل غیرعادی جابه‌جا شد.';

  @override
  String get reason_mock_location => 'موقعیت شبیه‌سازی‌شده قابل قبول نیست.';

  @override
  String get reason_other => 'شرایط پاداش کامل نشد.';

  @override
  String get visitTitle => 'بازدید';

  @override
  String get visitInside => 'داخل محدوده شعبه هستی';

  @override
  String get visitOutside => 'بیرون از محدوده شعبه‌ای';

  @override
  String get visitLocating => 'در حال پیدا کردن موقعیت…';

  @override
  String visitStay(String done, String total) {
    return '$done از $total';
  }

  @override
  String get visitKeepOpen =>
      'این صفحه را باز نگه دار. زمان حضور را سرور اندازه می‌گیرد، نه گوشی.';

  @override
  String get visitScanQr => 'اسکن QR صندوق';

  @override
  String get visitQrDone => 'QR شعبه تأیید شد';

  @override
  String get visitQrHint =>
      'کد روی صفحه صندوق هر ۳۰ ثانیه عوض می‌شود. عکس آن کار نمی‌کند.';

  @override
  String get visitRewarded => 'بازدید تأیید شد!';

  @override
  String visitRewardedBody(String n) {
    return '$n امتیاز پس از دوره بررسی به کیف پولت اضافه می‌شود.';
  }

  @override
  String get visitCouponReceived => 'کوپن هدیه به کوپن‌هایت اضافه شد';

  @override
  String get visitRejected => 'بازدید تأیید نشد';

  @override
  String get visitExpired => 'این بازدید منقضی شد';

  @override
  String get visitBack => 'بازگشت';

  @override
  String get qrScanTitle => 'اسکن QR شعبه';

  @override
  String get qrScanHint => 'کد روی صفحه صندوق را داخل کادر بگیر.';

  @override
  String get couponsTitle => 'کوپن‌ها';

  @override
  String get couponsMine => 'کوپن‌های من';

  @override
  String get couponsAvailable => 'دریافت با امتیاز';

  @override
  String get couponsEmpty =>
      'هنوز کوپنی نداری. با بازدید از شعبه‌های اسپانسر کوپن هدیه بگیر.';

  @override
  String get couponsAvailableEmpty => 'فعلاً کوپنی برای دریافت نیست.';

  @override
  String get couponCode => 'کد کوپن';

  @override
  String get couponShowCashier => 'این کد را به صندوق‌دار نشان بده.';

  @override
  String couponExpires(String date) {
    return 'اعتبار تا $date';
  }

  @override
  String couponUsedAt(String date) {
    return 'استفاده‌شده در $date';
  }

  @override
  String couponClaim(String n) {
    return 'دریافت با $n امتیاز';
  }

  @override
  String get couponClaimFree => 'دریافت رایگان';

  @override
  String couponClaimConfirm(String n) {
    return '$n امتیاز از کیف پولت کم می‌شود. ادامه می‌دهی؟';
  }

  @override
  String get couponClaimed => 'کوپن به کوپن‌هایت اضافه شد.';

  @override
  String couponRemaining(String n) {
    return '$n عدد باقی مانده';
  }

  @override
  String get couponTerms => 'شرایط استفاده';

  @override
  String couponOnlineCode(String code) {
    return 'کد خرید آنلاین: $code';
  }

  @override
  String get couponCopied => 'کد کپی شد.';

  @override
  String get permCameraTitle => 'دسترسی به دوربین';

  @override
  String get permCameraBody =>
      'برای اسکن QR صندوق شعبه. دوربین فقط در همین صفحه روشن می‌شود و تصویری ذخیره نمی‌شود.';

  @override
  String get rewardsNearby => 'جایزه‌های اطراف';

  @override
  String get rewardsCoupons => 'کوپن‌ها';

  @override
  String get profileCoupons => 'کوپن‌های من';

  @override
  String visitOfTotal(String total) {
    return 'از $total';
  }

  @override
  String get adLabel => 'تبلیغ';

  @override
  String get rewardedCardTitle => 'تبلیغ ببین، امتیاز بگیر';

  @override
  String rewardedCardBody(String n) {
    return 'امروز $n بار دیگر';
  }

  @override
  String get rewardedTitle => 'تبلیغ جایزه‌دار';

  @override
  String rewardedWait(String s) {
    return '$s ثانیه تا دریافت امتیاز';
  }

  @override
  String rewardedClaim(String n) {
    return 'دریافت $n امتیاز';
  }

  @override
  String get rewardedDone => 'امتیاز ثبت شد';

  @override
  String rewardedDoneBody(String n) {
    return '$n امتیاز پس از دوره بررسی به کیف پولت اضافه می‌شود.';
  }

  @override
  String get rewardedFailed => 'این بار امتیازی ثبت نشد.';

  @override
  String get rewardedLeaveHint =>
      'اگر قبل از پایان زمان خارج شوی، امتیازی ثبت نمی‌شود.';
}
