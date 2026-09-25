import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_fa.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[Locale('fa')];

  /// No description provided for @appName.
  ///
  /// In fa, this message translates to:
  /// **'گام‌یار'**
  String get appName;

  /// No description provided for @commonRetry.
  ///
  /// In fa, this message translates to:
  /// **'تلاش دوباره'**
  String get commonRetry;

  /// No description provided for @commonLoading.
  ///
  /// In fa, this message translates to:
  /// **'در حال بارگذاری'**
  String get commonLoading;

  /// No description provided for @commonSave.
  ///
  /// In fa, this message translates to:
  /// **'ذخیره'**
  String get commonSave;

  /// No description provided for @commonCancel.
  ///
  /// In fa, this message translates to:
  /// **'انصراف'**
  String get commonCancel;

  /// No description provided for @commonConfirm.
  ///
  /// In fa, this message translates to:
  /// **'تأیید'**
  String get commonConfirm;

  /// No description provided for @commonContinue.
  ///
  /// In fa, this message translates to:
  /// **'ادامه'**
  String get commonContinue;

  /// No description provided for @commonSkip.
  ///
  /// In fa, this message translates to:
  /// **'رد شدن'**
  String get commonSkip;

  /// No description provided for @commonClose.
  ///
  /// In fa, this message translates to:
  /// **'بستن'**
  String get commonClose;

  /// No description provided for @commonSaved.
  ///
  /// In fa, this message translates to:
  /// **'تغییرات ذخیره شد.'**
  String get commonSaved;

  /// No description provided for @commonSoon.
  ///
  /// In fa, this message translates to:
  /// **'به‌زودی'**
  String get commonSoon;

  /// No description provided for @navHome.
  ///
  /// In fa, this message translates to:
  /// **'خانه'**
  String get navHome;

  /// No description provided for @navActivity.
  ///
  /// In fa, this message translates to:
  /// **'فعالیت'**
  String get navActivity;

  /// No description provided for @navRewards.
  ///
  /// In fa, this message translates to:
  /// **'جایزه‌ها'**
  String get navRewards;

  /// No description provided for @navStore.
  ///
  /// In fa, this message translates to:
  /// **'فروشگاه'**
  String get navStore;

  /// No description provided for @navProfile.
  ///
  /// In fa, this message translates to:
  /// **'پروفایل'**
  String get navProfile;

  /// No description provided for @onboardingMoveTitle.
  ///
  /// In fa, this message translates to:
  /// **'حرکت کن'**
  String get onboardingMoveTitle;

  /// No description provided for @onboardingMoveBody.
  ///
  /// In fa, this message translates to:
  /// **'مثل همیشه راه برو. گام‌یار قدم‌هایت را در پس‌زمینه و با کمترین مصرف باتری ثبت می‌کند.'**
  String get onboardingMoveBody;

  /// No description provided for @onboardingEarnTitle.
  ///
  /// In fa, this message translates to:
  /// **'امتیاز بگیر'**
  String get onboardingEarnTitle;

  /// No description provided for @onboardingEarnBody.
  ///
  /// In fa, this message translates to:
  /// **'هر قدم تأییدشده به امتیاز تبدیل می‌شود؛ ارزش ریالی امتیازت را همیشه می‌بینی.'**
  String get onboardingEarnBody;

  /// No description provided for @onboardingRewardTitle.
  ///
  /// In fa, this message translates to:
  /// **'جایزه دریافت کن'**
  String get onboardingRewardTitle;

  /// No description provided for @onboardingRewardBody.
  ///
  /// In fa, this message translates to:
  /// **'با رسیدن به هدف روزانه، زنجیره روزها و چالش‌ها جایزه بیشتری بگیر.'**
  String get onboardingRewardBody;

  /// No description provided for @onboardingStoreTitle.
  ///
  /// In fa, this message translates to:
  /// **'از فروشگاه خرید کن'**
  String get onboardingStoreTitle;

  /// No description provided for @onboardingStoreBody.
  ///
  /// In fa, this message translates to:
  /// **'با امتیازهایت کارت هدیه، کوپن و کالا بخر.'**
  String get onboardingStoreBody;

  /// No description provided for @onboardingNearbyTitle.
  ///
  /// In fa, this message translates to:
  /// **'جایزه‌های اطرافت را پیدا کن'**
  String get onboardingNearbyTitle;

  /// No description provided for @onboardingNearbyBody.
  ///
  /// In fa, this message translates to:
  /// **'به فروشگاه‌های همکار سر بزن و جایزه ویژه بگیر.'**
  String get onboardingNearbyBody;

  /// No description provided for @onboardingStart.
  ///
  /// In fa, this message translates to:
  /// **'شروع کنیم'**
  String get onboardingStart;

  /// No description provided for @authPhoneTitle.
  ///
  /// In fa, this message translates to:
  /// **'ورود به گام‌یار'**
  String get authPhoneTitle;

  /// No description provided for @authPhoneSubtitle.
  ///
  /// In fa, this message translates to:
  /// **'شماره موبایلت را وارد کن تا کد تأیید برایت پیامک شود.'**
  String get authPhoneSubtitle;

  /// No description provided for @authPhoneLabel.
  ///
  /// In fa, this message translates to:
  /// **'شماره موبایل'**
  String get authPhoneLabel;

  /// No description provided for @authPhoneHint.
  ///
  /// In fa, this message translates to:
  /// **'۰۹۱۲ ۳۴۵ ۶۷۸۹'**
  String get authPhoneHint;

  /// No description provided for @authPhoneInvalid.
  ///
  /// In fa, this message translates to:
  /// **'شماره موبایل معتبر نیست.'**
  String get authPhoneInvalid;

  /// No description provided for @authSendCode.
  ///
  /// In fa, this message translates to:
  /// **'دریافت کد'**
  String get authSendCode;

  /// No description provided for @authTermsNote.
  ///
  /// In fa, this message translates to:
  /// **'ورود به معنی پذیرش قوانین و سیاست حریم خصوصی گام‌یار است.'**
  String get authTermsNote;

  /// No description provided for @authTermsLink.
  ///
  /// In fa, this message translates to:
  /// **'قوانین و حریم خصوصی'**
  String get authTermsLink;

  /// No description provided for @authOtpTitle.
  ///
  /// In fa, this message translates to:
  /// **'کد تأیید'**
  String get authOtpTitle;

  /// No description provided for @authOtpSubtitle.
  ///
  /// In fa, this message translates to:
  /// **'کد ارسال‌شده به {phone} را وارد کن.'**
  String authOtpSubtitle(String phone);

  /// No description provided for @authOtpLabel.
  ///
  /// In fa, this message translates to:
  /// **'کد تأیید'**
  String get authOtpLabel;

  /// No description provided for @authEditPhone.
  ///
  /// In fa, this message translates to:
  /// **'ویرایش شماره'**
  String get authEditPhone;

  /// No description provided for @authResend.
  ///
  /// In fa, this message translates to:
  /// **'ارسال دوباره کد'**
  String get authResend;

  /// No description provided for @authResendIn.
  ///
  /// In fa, this message translates to:
  /// **'ارسال دوباره تا {seconds} ثانیه دیگر'**
  String authResendIn(String seconds);

  /// No description provided for @authReferralToggle.
  ///
  /// In fa, this message translates to:
  /// **'کد معرف دارم'**
  String get authReferralToggle;

  /// No description provided for @authReferralLabel.
  ///
  /// In fa, this message translates to:
  /// **'کد معرف (اختیاری)'**
  String get authReferralLabel;

  /// No description provided for @authVerify.
  ///
  /// In fa, this message translates to:
  /// **'تأیید و ورود'**
  String get authVerify;

  /// No description provided for @authPreparingDevice.
  ///
  /// In fa, this message translates to:
  /// **'آماده‌سازی امن دستگاه…'**
  String get authPreparingDevice;

  /// No description provided for @homeGreeting.
  ///
  /// In fa, this message translates to:
  /// **'سلام {name}'**
  String homeGreeting(String name);

  /// No description provided for @homeToday.
  ///
  /// In fa, this message translates to:
  /// **'امروز، {date}'**
  String homeToday(String date);

  /// No description provided for @homeRemaining.
  ///
  /// In fa, this message translates to:
  /// **'{steps} قدم تا هدف امروز'**
  String homeRemaining(String steps);

  /// No description provided for @homeGoalReached.
  ///
  /// In fa, this message translates to:
  /// **'هدف امروز را کامل کردی'**
  String get homeGoalReached;

  /// No description provided for @homeDistance.
  ///
  /// In fa, this message translates to:
  /// **'مسافت'**
  String get homeDistance;

  /// No description provided for @homeCalories.
  ///
  /// In fa, this message translates to:
  /// **'کالری تخمینی'**
  String get homeCalories;

  /// No description provided for @homeActiveTime.
  ///
  /// In fa, this message translates to:
  /// **'زمان فعالیت'**
  String get homeActiveTime;

  /// No description provided for @homeUnitKm.
  ///
  /// In fa, this message translates to:
  /// **'کیلومتر'**
  String get homeUnitKm;

  /// No description provided for @homeUnitKcal.
  ///
  /// In fa, this message translates to:
  /// **'کیلوکالری'**
  String get homeUnitKcal;

  /// No description provided for @homeUnitMin.
  ///
  /// In fa, this message translates to:
  /// **'دقیقه'**
  String get homeUnitMin;

  /// No description provided for @homePointsToday.
  ///
  /// In fa, this message translates to:
  /// **'امتیاز امروز'**
  String get homePointsToday;

  /// No description provided for @homePointsValue.
  ///
  /// In fa, this message translates to:
  /// **'ارزش تقریبی'**
  String get homePointsValue;

  /// No description provided for @homeStreak.
  ///
  /// In fa, this message translates to:
  /// **'{days} روز متوالی'**
  String homeStreak(String days);

  /// No description provided for @homeNoStreak.
  ///
  /// In fa, this message translates to:
  /// **'امروز اولین روز زنجیره‌ات را بساز'**
  String get homeNoStreak;

  /// No description provided for @profileTitle.
  ///
  /// In fa, this message translates to:
  /// **'پروفایل'**
  String get profileTitle;

  /// No description provided for @profileLevel.
  ///
  /// In fa, this message translates to:
  /// **'سطح {level}'**
  String profileLevel(String level);

  /// No description provided for @profileJoined.
  ///
  /// In fa, this message translates to:
  /// **'عضو از {date}'**
  String profileJoined(String date);

  /// No description provided for @profileEdit.
  ///
  /// In fa, this message translates to:
  /// **'ویرایش پروفایل'**
  String get profileEdit;

  /// No description provided for @profileSectionActivity.
  ///
  /// In fa, this message translates to:
  /// **'فعالیت'**
  String get profileSectionActivity;

  /// No description provided for @profileDailyGoal.
  ///
  /// In fa, this message translates to:
  /// **'هدف روزانه'**
  String get profileDailyGoal;

  /// No description provided for @profileWaterGoal.
  ///
  /// In fa, this message translates to:
  /// **'هدف مصرف آب'**
  String get profileWaterGoal;

  /// No description provided for @profileSectionPrivacy.
  ///
  /// In fa, this message translates to:
  /// **'حریم خصوصی و امنیت'**
  String get profileSectionPrivacy;

  /// No description provided for @profileLeaderboardVisible.
  ///
  /// In fa, this message translates to:
  /// **'نمایش من در رتبه‌بندی'**
  String get profileLeaderboardVisible;

  /// No description provided for @profileLeaderboardVisibleHint.
  ///
  /// In fa, this message translates to:
  /// **'فقط نام نمایشی، تصویر و سطح نمایش داده می‌شود.'**
  String get profileLeaderboardVisibleHint;

  /// No description provided for @profileNotifications.
  ///
  /// In fa, this message translates to:
  /// **'اعلان‌ها'**
  String get profileNotifications;

  /// No description provided for @profileDevices.
  ///
  /// In fa, this message translates to:
  /// **'دستگاه‌های من'**
  String get profileDevices;

  /// No description provided for @profileDeleteAccount.
  ///
  /// In fa, this message translates to:
  /// **'حذف حساب کاربری'**
  String get profileDeleteAccount;

  /// No description provided for @profileSectionAbout.
  ///
  /// In fa, this message translates to:
  /// **'درباره'**
  String get profileSectionAbout;

  /// No description provided for @profileHowToEarn.
  ///
  /// In fa, this message translates to:
  /// **'نحوه دریافت امتیاز'**
  String get profileHowToEarn;

  /// No description provided for @profileRewardRules.
  ///
  /// In fa, this message translates to:
  /// **'قوانین پاداش'**
  String get profileRewardRules;

  /// No description provided for @profileTerms.
  ///
  /// In fa, this message translates to:
  /// **'قوانین و مقررات'**
  String get profileTerms;

  /// No description provided for @profilePrivacy.
  ///
  /// In fa, this message translates to:
  /// **'حریم خصوصی'**
  String get profilePrivacy;

  /// No description provided for @profileFaq.
  ///
  /// In fa, this message translates to:
  /// **'سوالات متداول'**
  String get profileFaq;

  /// No description provided for @profileAbout.
  ///
  /// In fa, this message translates to:
  /// **'درباره گام‌یار'**
  String get profileAbout;

  /// No description provided for @profileLogout.
  ///
  /// In fa, this message translates to:
  /// **'خروج از حساب'**
  String get profileLogout;

  /// No description provided for @profileLogoutConfirm.
  ///
  /// In fa, this message translates to:
  /// **'از حساب کاربری خارج می‌شوی؟'**
  String get profileLogoutConfirm;

  /// No description provided for @profileVersion.
  ///
  /// In fa, this message translates to:
  /// **'نسخه {version}'**
  String profileVersion(String version);

  /// No description provided for @editProfileTitle.
  ///
  /// In fa, this message translates to:
  /// **'ویرایش پروفایل'**
  String get editProfileTitle;

  /// No description provided for @editDisplayName.
  ///
  /// In fa, this message translates to:
  /// **'نام نمایشی'**
  String get editDisplayName;

  /// No description provided for @editDisplayNameHint.
  ///
  /// In fa, this message translates to:
  /// **'مثلاً سارا'**
  String get editDisplayNameHint;

  /// No description provided for @editBirthYear.
  ///
  /// In fa, this message translates to:
  /// **'سال تولد (میلادی)'**
  String get editBirthYear;

  /// No description provided for @editHeight.
  ///
  /// In fa, this message translates to:
  /// **'قد (سانتی‌متر)'**
  String get editHeight;

  /// No description provided for @editWeight.
  ///
  /// In fa, this message translates to:
  /// **'وزن (کیلوگرم)'**
  String get editWeight;

  /// No description provided for @editGender.
  ///
  /// In fa, this message translates to:
  /// **'جنسیت (اختیاری)'**
  String get editGender;

  /// No description provided for @editGenderFemale.
  ///
  /// In fa, this message translates to:
  /// **'زن'**
  String get editGenderFemale;

  /// No description provided for @editGenderMale.
  ///
  /// In fa, this message translates to:
  /// **'مرد'**
  String get editGenderMale;

  /// No description provided for @editGenderNone.
  ///
  /// In fa, this message translates to:
  /// **'ترجیح می‌دهم نگویم'**
  String get editGenderNone;

  /// No description provided for @editBodyNote.
  ///
  /// In fa, this message translates to:
  /// **'قد، وزن و سن فقط برای تخمین کالری و مسافت استفاده می‌شوند و به هیچ‌کس نمایش داده نمی‌شوند.'**
  String get editBodyNote;

  /// No description provided for @goalTitle.
  ///
  /// In fa, this message translates to:
  /// **'هدف روزانه'**
  String get goalTitle;

  /// No description provided for @goalSubtitle.
  ///
  /// In fa, this message translates to:
  /// **'هدفی انتخاب کن که هر روز بتوانی به آن برسی. بعداً هم قابل تغییر است.'**
  String get goalSubtitle;

  /// No description provided for @goalCustom.
  ///
  /// In fa, this message translates to:
  /// **'مقدار دلخواه'**
  String get goalCustom;

  /// No description provided for @goalSteps.
  ///
  /// In fa, this message translates to:
  /// **'{steps} قدم'**
  String goalSteps(String steps);

  /// No description provided for @goalRange.
  ///
  /// In fa, this message translates to:
  /// **'بین {min} و {max} قدم'**
  String goalRange(String min, String max);

  /// No description provided for @waterGoalTitle.
  ///
  /// In fa, this message translates to:
  /// **'هدف مصرف آب'**
  String get waterGoalTitle;

  /// No description provided for @waterGoalNote.
  ///
  /// In fa, this message translates to:
  /// **'این عدد تقریبی است و توصیه پزشکی نیست.'**
  String get waterGoalNote;

  /// No description provided for @waterGlasses.
  ///
  /// In fa, this message translates to:
  /// **'{glasses} لیوان'**
  String waterGlasses(String glasses);

  /// No description provided for @notificationsTitle.
  ///
  /// In fa, this message translates to:
  /// **'اعلان‌ها'**
  String get notificationsTitle;

  /// No description provided for @notificationsMandatory.
  ///
  /// In fa, this message translates to:
  /// **'اعلان وضعیت سفارش برای اطلاع از خریدهایت همیشه فعال است.'**
  String get notificationsMandatory;

  /// No description provided for @devicesTitle.
  ///
  /// In fa, this message translates to:
  /// **'دستگاه‌های من'**
  String get devicesTitle;

  /// No description provided for @devicesCurrent.
  ///
  /// In fa, this message translates to:
  /// **'همین دستگاه'**
  String get devicesCurrent;

  /// No description provided for @devicesLastSeen.
  ///
  /// In fa, this message translates to:
  /// **'آخرین استفاده: {time}'**
  String devicesLastSeen(String time);

  /// No description provided for @devicesRevoke.
  ///
  /// In fa, this message translates to:
  /// **'خروج'**
  String get devicesRevoke;

  /// No description provided for @devicesRevokeConfirm.
  ///
  /// In fa, this message translates to:
  /// **'این دستگاه از حساب شما خارج شود؟'**
  String get devicesRevokeConfirm;

  /// No description provided for @devicesEmpty.
  ///
  /// In fa, this message translates to:
  /// **'دستگاه دیگری به حساب شما متصل نیست.'**
  String get devicesEmpty;

  /// No description provided for @deleteAccountTitle.
  ///
  /// In fa, this message translates to:
  /// **'حذف حساب کاربری'**
  String get deleteAccountTitle;

  /// No description provided for @deleteAccountBody.
  ///
  /// In fa, this message translates to:
  /// **'با درخواست حذف، حساب شما پس از {days} روز حذف می‌شود و تا آن زمان می‌توانید انصراف دهید. اطلاعات شخصی حذف و سوابق مالی به‌صورت ناشناس نگهداری می‌شوند.'**
  String deleteAccountBody(String days);

  /// No description provided for @deleteAccountConfirm.
  ///
  /// In fa, this message translates to:
  /// **'درخواست حذف حساب'**
  String get deleteAccountConfirm;

  /// No description provided for @deleteAccountPending.
  ///
  /// In fa, this message translates to:
  /// **'حساب شما در تاریخ {date} حذف می‌شود.'**
  String deleteAccountPending(String date);

  /// No description provided for @deleteAccountCancel.
  ///
  /// In fa, this message translates to:
  /// **'انصراف از حذف حساب'**
  String get deleteAccountCancel;

  /// No description provided for @updateRequiredTitle.
  ///
  /// In fa, this message translates to:
  /// **'نسخه جدید لازم است'**
  String get updateRequiredTitle;

  /// No description provided for @updateRequiredBody.
  ///
  /// In fa, this message translates to:
  /// **'برای ادامه، گام‌یار را به‌روزرسانی کنید.'**
  String get updateRequiredBody;

  /// No description provided for @accountBlockedTitle.
  ///
  /// In fa, this message translates to:
  /// **'دسترسی محدود شده است'**
  String get accountBlockedTitle;

  /// No description provided for @accountBlockedContact.
  ///
  /// In fa, this message translates to:
  /// **'برای پیگیری با پشتیبانی تماس بگیرید.'**
  String get accountBlockedContact;

  /// No description provided for @permAllow.
  ///
  /// In fa, this message translates to:
  /// **'اجازه می‌دهم'**
  String get permAllow;

  /// No description provided for @permNotNow.
  ///
  /// In fa, this message translates to:
  /// **'فعلاً نه'**
  String get permNotNow;

  /// No description provided for @permOpenSettings.
  ///
  /// In fa, this message translates to:
  /// **'رفتن به تنظیمات'**
  String get permOpenSettings;

  /// No description provided for @permOpenSettingsHint.
  ///
  /// In fa, this message translates to:
  /// **'این دسترسی قبلاً رد شده است. برای فعال‌سازی، از تنظیمات گوشی آن را روشن کنید.'**
  String get permOpenSettingsHint;

  /// No description provided for @permActivityTitle.
  ///
  /// In fa, this message translates to:
  /// **'ثبت خودکار قدم‌ها'**
  String get permActivityTitle;

  /// No description provided for @permActivityBody.
  ///
  /// In fa, this message translates to:
  /// **'گام‌یار برای شمردن قدم‌هایت به «فعالیت بدنی» دسترسی لازم دارد. این کار با شمارنده قدم خود گوشی و با کمترین مصرف باتری انجام می‌شود؛ موقعیت مکانی تو ثبت نمی‌شود.'**
  String get permActivityBody;

  /// No description provided for @permNotificationsTitle.
  ///
  /// In fa, this message translates to:
  /// **'اعلان پیاده‌روی'**
  String get permNotificationsTitle;

  /// No description provided for @permNotificationsBody.
  ///
  /// In fa, this message translates to:
  /// **'وقتی پیاده‌روی را شروع می‌کنی، یک اعلان ثابت نشان می‌دهد که ثبت قدم فعال است و تعداد قدم‌ها را می‌بینی.'**
  String get permNotificationsBody;

  /// No description provided for @permLocationTitle.
  ///
  /// In fa, this message translates to:
  /// **'ثبت مسیر پیاده‌روی'**
  String get permLocationTitle;

  /// No description provided for @permLocationBody.
  ///
  /// In fa, this message translates to:
  /// **'برای محاسبه دقیق‌تر مسافت، موقعیت مکانی فقط در همین پیاده‌روی و فقط وقتی خودت آن را فعال کنی استفاده می‌شود. مسیر کامل تو به سرور ارسال نمی‌شود.'**
  String get permLocationBody;

  /// No description provided for @trackingOffTitle.
  ///
  /// In fa, this message translates to:
  /// **'ثبت خودکار قدم‌ها خاموش است'**
  String get trackingOffTitle;

  /// No description provided for @trackingOffBody.
  ///
  /// In fa, this message translates to:
  /// **'اجازه دسترسی به فعالیت بدنی را بده تا قدم‌هایت بدون باز بودن برنامه ثبت شوند.'**
  String get trackingOffBody;

  /// No description provided for @trackingEnable.
  ///
  /// In fa, this message translates to:
  /// **'فعال‌سازی'**
  String get trackingEnable;

  /// No description provided for @trackingNoSensor.
  ///
  /// In fa, this message translates to:
  /// **'این گوشی شمارنده قدم ندارد؛ ثبت خودکار قدم امکان‌پذیر نیست.'**
  String get trackingNoSensor;

  /// No description provided for @homeAwaiting.
  ///
  /// In fa, this message translates to:
  /// **'{steps} قدم در حال بررسی'**
  String homeAwaiting(String steps);

  /// No description provided for @homeStartWalk.
  ///
  /// In fa, this message translates to:
  /// **'شروع پیاده‌روی'**
  String get homeStartWalk;

  /// No description provided for @homeWalkInProgress.
  ///
  /// In fa, this message translates to:
  /// **'پیاده‌روی در حال ثبت'**
  String get homeWalkInProgress;

  /// No description provided for @homeThisWeek.
  ///
  /// In fa, this message translates to:
  /// **'این هفته'**
  String get homeThisWeek;

  /// No description provided for @homeSyncedAt.
  ///
  /// In fa, this message translates to:
  /// **'به‌روزرسانی {time}'**
  String homeSyncedAt(String time);

  /// No description provided for @activityTitle.
  ///
  /// In fa, this message translates to:
  /// **'فعالیت'**
  String get activityTitle;

  /// No description provided for @activityToday.
  ///
  /// In fa, this message translates to:
  /// **'امروز'**
  String get activityToday;

  /// No description provided for @activityHourly.
  ///
  /// In fa, this message translates to:
  /// **'قدم‌ها در طول روز'**
  String get activityHourly;

  /// No description provided for @activityTimeline.
  ///
  /// In fa, this message translates to:
  /// **'زمان‌بندی'**
  String get activityTimeline;

  /// No description provided for @activityEmptyTitle.
  ///
  /// In fa, this message translates to:
  /// **'هنوز قدمی ثبت نشده'**
  String get activityEmptyTitle;

  /// No description provided for @activityEmptyBody.
  ///
  /// In fa, this message translates to:
  /// **'کمی راه برو؛ قدم‌هایت اینجا نمایش داده می‌شوند.'**
  String get activityEmptyBody;

  /// No description provided for @activitySteps.
  ///
  /// In fa, this message translates to:
  /// **'قدم'**
  String get activitySteps;

  /// No description provided for @activityPassive.
  ///
  /// In fa, this message translates to:
  /// **'ثبت خودکار'**
  String get activityPassive;

  /// No description provided for @activityActive.
  ///
  /// In fa, this message translates to:
  /// **'پیاده‌روی'**
  String get activityActive;

  /// No description provided for @sessionTitle.
  ///
  /// In fa, this message translates to:
  /// **'جزئیات فعالیت'**
  String get sessionTitle;

  /// No description provided for @sessionDuration.
  ///
  /// In fa, this message translates to:
  /// **'مدت'**
  String get sessionDuration;

  /// No description provided for @sessionPerMinute.
  ///
  /// In fa, this message translates to:
  /// **'قدم در هر دقیقه'**
  String get sessionPerMinute;

  /// No description provided for @sessionVerified.
  ///
  /// In fa, this message translates to:
  /// **'قدم تأییدشده'**
  String get sessionVerified;

  /// No description provided for @sessionSamplesExpired.
  ///
  /// In fa, this message translates to:
  /// **'جزئیات دقیقه‌ای فقط تا ۳۰ روز نگهداری می‌شوند.'**
  String get sessionSamplesExpired;

  /// No description provided for @walkTitle.
  ///
  /// In fa, this message translates to:
  /// **'پیاده‌روی'**
  String get walkTitle;

  /// No description provided for @walkIntro.
  ///
  /// In fa, this message translates to:
  /// **'پیاده‌روی را شروع کن تا قدم‌ها، زمان و مسافتت دقیق‌تر ثبت شود. می‌توانی برنامه را ببندی؛ ثبت ادامه دارد.'**
  String get walkIntro;

  /// No description provided for @walkGpsToggle.
  ///
  /// In fa, this message translates to:
  /// **'ثبت مسیر با GPS'**
  String get walkGpsToggle;

  /// No description provided for @walkGpsHint.
  ///
  /// In fa, this message translates to:
  /// **'مسافت دقیق‌تر، مصرف باتری بیشتر'**
  String get walkGpsHint;

  /// No description provided for @walkStart.
  ///
  /// In fa, this message translates to:
  /// **'شروع'**
  String get walkStart;

  /// No description provided for @walkStop.
  ///
  /// In fa, this message translates to:
  /// **'پایان پیاده‌روی'**
  String get walkStop;

  /// No description provided for @walkSaving.
  ///
  /// In fa, this message translates to:
  /// **'در حال ذخیره…'**
  String get walkSaving;

  /// No description provided for @walkElapsed.
  ///
  /// In fa, this message translates to:
  /// **'زمان'**
  String get walkElapsed;

  /// No description provided for @walkDistance.
  ///
  /// In fa, this message translates to:
  /// **'مسافت'**
  String get walkDistance;

  /// No description provided for @walkDoneTitle.
  ///
  /// In fa, this message translates to:
  /// **'آفرین! پیاده‌روی ثبت شد'**
  String get walkDoneTitle;

  /// No description provided for @walkDoneBody.
  ///
  /// In fa, this message translates to:
  /// **'{steps} قدم در {minutes} دقیقه. امتیاز پس از بررسی به حسابت اضافه می‌شود.'**
  String walkDoneBody(String steps, String minutes);

  /// No description provided for @walkDoneOffline.
  ///
  /// In fa, this message translates to:
  /// **'اینترنت در دسترس نیست؛ پیاده‌روی ذخیره شد و پس از اتصال ارسال می‌شود.'**
  String get walkDoneOffline;

  /// No description provided for @walkDone.
  ///
  /// In fa, this message translates to:
  /// **'باشه'**
  String get walkDone;

  /// No description provided for @walkNoSensor.
  ///
  /// In fa, this message translates to:
  /// **'این گوشی شمارنده قدم ندارد.'**
  String get walkNoSensor;

  /// No description provided for @walkNeedsPermission.
  ///
  /// In fa, this message translates to:
  /// **'برای شروع پیاده‌روی، دسترسی فعالیت بدنی لازم است.'**
  String get walkNeedsPermission;

  /// No description provided for @walkFailed.
  ///
  /// In fa, this message translates to:
  /// **'شروع پیاده‌روی ممکن نشد. دوباره تلاش کن.'**
  String get walkFailed;

  /// No description provided for @pointsUnit.
  ///
  /// In fa, this message translates to:
  /// **'امتیاز'**
  String get pointsUnit;

  /// No description provided for @pointsPlus.
  ///
  /// In fa, this message translates to:
  /// **'+{points} امتیاز'**
  String pointsPlus(String points);

  /// No description provided for @homePointsCard.
  ///
  /// In fa, this message translates to:
  /// **'امتیاز امروز'**
  String get homePointsCard;

  /// No description provided for @homeWalletLink.
  ///
  /// In fa, this message translates to:
  /// **'کیف پول'**
  String get homeWalletLink;

  /// No description provided for @walletTitle.
  ///
  /// In fa, this message translates to:
  /// **'کیف پول'**
  String get walletTitle;

  /// No description provided for @walletAvailable.
  ///
  /// In fa, this message translates to:
  /// **'امتیاز قابل استفاده'**
  String get walletAvailable;

  /// No description provided for @walletValue.
  ///
  /// In fa, this message translates to:
  /// **'ارزش تقریبی: {value}'**
  String walletValue(String value);

  /// No description provided for @walletPending.
  ///
  /// In fa, this message translates to:
  /// **'در حال بررسی'**
  String get walletPending;

  /// No description provided for @walletPendingHint.
  ///
  /// In fa, this message translates to:
  /// **'امتیازهای جدید پس از بررسی امنیتی قابل استفاده می‌شوند.'**
  String get walletPendingHint;

  /// No description provided for @walletNextRelease.
  ///
  /// In fa, this message translates to:
  /// **'آزادسازی بعدی: {time}'**
  String walletNextRelease(String time);

  /// No description provided for @walletLifetime.
  ///
  /// In fa, this message translates to:
  /// **'کل دریافتی'**
  String get walletLifetime;

  /// No description provided for @walletSpent.
  ///
  /// In fa, this message translates to:
  /// **'کل مصرف'**
  String get walletSpent;

  /// No description provided for @walletRate.
  ///
  /// In fa, this message translates to:
  /// **'هر امتیاز ≈ {rial}'**
  String walletRate(String rial);

  /// No description provided for @walletHistory.
  ///
  /// In fa, this message translates to:
  /// **'تاریخچه'**
  String get walletHistory;

  /// No description provided for @walletFilterAll.
  ///
  /// In fa, this message translates to:
  /// **'همه'**
  String get walletFilterAll;

  /// No description provided for @walletFilterEarned.
  ///
  /// In fa, this message translates to:
  /// **'دریافتی'**
  String get walletFilterEarned;

  /// No description provided for @walletFilterSpent.
  ///
  /// In fa, this message translates to:
  /// **'مصرف'**
  String get walletFilterSpent;

  /// No description provided for @walletFilterPurchase.
  ///
  /// In fa, this message translates to:
  /// **'خرید'**
  String get walletFilterPurchase;

  /// No description provided for @walletFilterReward.
  ///
  /// In fa, this message translates to:
  /// **'جایزه'**
  String get walletFilterReward;

  /// No description provided for @walletFilterSponsor.
  ///
  /// In fa, this message translates to:
  /// **'اسپانسر'**
  String get walletFilterSponsor;

  /// No description provided for @walletFilterAdjustment.
  ///
  /// In fa, this message translates to:
  /// **'اصلاح'**
  String get walletFilterAdjustment;

  /// No description provided for @walletEmpty.
  ///
  /// In fa, this message translates to:
  /// **'هنوز تراکنشی نداری'**
  String get walletEmpty;

  /// No description provided for @walletEmptyBody.
  ///
  /// In fa, this message translates to:
  /// **'با راه رفتن اولین امتیازت را بگیر.'**
  String get walletEmptyBody;

  /// No description provided for @walletReversed.
  ///
  /// In fa, this message translates to:
  /// **'لغو شد'**
  String get walletReversed;

  /// No description provided for @rewardsTitle.
  ///
  /// In fa, this message translates to:
  /// **'جایزه‌ها'**
  String get rewardsTitle;

  /// No description provided for @rewardsToday.
  ///
  /// In fa, this message translates to:
  /// **'امتیاز امروز'**
  String get rewardsToday;

  /// No description provided for @rewardsTodayOf.
  ///
  /// In fa, this message translates to:
  /// **'از سقف {cap} امتیاز روزانه'**
  String rewardsTodayOf(String cap);

  /// No description provided for @rewardsHowTitle.
  ///
  /// In fa, this message translates to:
  /// **'چطور امتیاز بیشتری بگیری؟'**
  String get rewardsHowTitle;

  /// No description provided for @rewardsRate.
  ///
  /// In fa, this message translates to:
  /// **'هر {steps} قدم تأییدشده'**
  String rewardsRate(String steps);

  /// No description provided for @rewardsRemaining.
  ///
  /// In fa, this message translates to:
  /// **'امروز تا {steps} قدم دیگر امتیاز دارد'**
  String rewardsRemaining(String steps);

  /// No description provided for @rewardsGoalBonus.
  ///
  /// In fa, this message translates to:
  /// **'رسیدن به هدف روزانه'**
  String get rewardsGoalBonus;

  /// No description provided for @rewardsGoalDone.
  ///
  /// In fa, this message translates to:
  /// **'دریافت شد'**
  String get rewardsGoalDone;

  /// No description provided for @rewardsStreak.
  ///
  /// In fa, this message translates to:
  /// **'{days} روز متوالی'**
  String rewardsStreak(String days);

  /// No description provided for @rewardsMultiplierNow.
  ///
  /// In fa, this message translates to:
  /// **'الان ضریب ×{factor} فعال است'**
  String rewardsMultiplierNow(String factor);

  /// No description provided for @rewardsUpcoming.
  ///
  /// In fa, this message translates to:
  /// **'روزهای ویژه'**
  String get rewardsUpcoming;

  /// No description provided for @rewardsRecent.
  ///
  /// In fa, this message translates to:
  /// **'آخرین جایزه‌ها'**
  String get rewardsRecent;

  /// No description provided for @rewardKindWalking.
  ///
  /// In fa, this message translates to:
  /// **'پاداش قدم'**
  String get rewardKindWalking;

  /// No description provided for @rewardKindGoal.
  ///
  /// In fa, this message translates to:
  /// **'پاداش هدف روزانه'**
  String get rewardKindGoal;

  /// No description provided for @rewardKindStreak.
  ///
  /// In fa, this message translates to:
  /// **'پاداش روزهای متوالی'**
  String get rewardKindStreak;

  /// No description provided for @rewardKindOther.
  ///
  /// In fa, this message translates to:
  /// **'جایزه'**
  String get rewardKindOther;

  /// No description provided for @rewardPending.
  ///
  /// In fa, this message translates to:
  /// **'در حال بررسی'**
  String get rewardPending;

  /// No description provided for @rewardDenied.
  ///
  /// In fa, this message translates to:
  /// **'سقف روزانه'**
  String get rewardDenied;

  /// No description provided for @rewardReversed.
  ///
  /// In fa, this message translates to:
  /// **'لغوشده'**
  String get rewardReversed;

  /// No description provided for @sessionConfidence.
  ///
  /// In fa, this message translates to:
  /// **'اطمینان'**
  String get sessionConfidence;

  /// No description provided for @sessionReward.
  ///
  /// In fa, this message translates to:
  /// **'امتیاز این فعالیت'**
  String get sessionReward;

  /// No description provided for @healthTitle.
  ///
  /// In fa, this message translates to:
  /// **'سلامت و آمار'**
  String get healthTitle;

  /// No description provided for @healthWeek.
  ///
  /// In fa, this message translates to:
  /// **'۷ روز اخیر'**
  String get healthWeek;

  /// No description provided for @healthMonth.
  ///
  /// In fa, this message translates to:
  /// **'۳۰ روز اخیر'**
  String get healthMonth;

  /// No description provided for @healthDisclaimer.
  ///
  /// In fa, this message translates to:
  /// **'این آمار برای انگیزه و پیگیری فعالیت است و جنبه تشخیص یا توصیه پزشکی ندارد.'**
  String get healthDisclaimer;

  /// No description provided for @healthAvgDaily.
  ///
  /// In fa, this message translates to:
  /// **'میانگین روزانه'**
  String get healthAvgDaily;

  /// No description provided for @healthAvgWeekly.
  ///
  /// In fa, this message translates to:
  /// **'میانگین هفتگی'**
  String get healthAvgWeekly;

  /// No description provided for @healthAvgMonthly.
  ///
  /// In fa, this message translates to:
  /// **'میانگین ماهانه'**
  String get healthAvgMonthly;

  /// No description provided for @healthTotal.
  ///
  /// In fa, this message translates to:
  /// **'مجموع'**
  String get healthTotal;

  /// No description provided for @healthGoalDays.
  ///
  /// In fa, this message translates to:
  /// **'{n} روز هدف کامل'**
  String healthGoalDays(String n);

  /// No description provided for @healthRecords.
  ///
  /// In fa, this message translates to:
  /// **'رکوردهای شخصی'**
  String get healthRecords;

  /// No description provided for @recordBestDay.
  ///
  /// In fa, this message translates to:
  /// **'بهترین روز'**
  String get recordBestDay;

  /// No description provided for @recordBestWeek.
  ///
  /// In fa, this message translates to:
  /// **'بهترین هفته'**
  String get recordBestWeek;

  /// No description provided for @recordBestSession.
  ///
  /// In fa, this message translates to:
  /// **'بهترین پیاده‌روی'**
  String get recordBestSession;

  /// No description provided for @healthStreak.
  ///
  /// In fa, this message translates to:
  /// **'زنجیره روزها'**
  String get healthStreak;

  /// No description provided for @healthStreakValue.
  ///
  /// In fa, this message translates to:
  /// **'{current} روز · بهترین: {longest}'**
  String healthStreakValue(String current, String longest);

  /// No description provided for @weeklyTitle.
  ///
  /// In fa, this message translates to:
  /// **'گزارش هفتگی'**
  String get weeklyTitle;

  /// No description provided for @weeklyThisWeek.
  ///
  /// In fa, this message translates to:
  /// **'این هفته'**
  String get weeklyThisWeek;

  /// No description provided for @weeklyGoalDays.
  ///
  /// In fa, this message translates to:
  /// **'{done} از {total} روز هدف تکمیل شده'**
  String weeklyGoalDays(String done, String total);

  /// No description provided for @weeklyChangeUp.
  ///
  /// In fa, this message translates to:
  /// **'{percent} بیشتر از هفته قبل'**
  String weeklyChangeUp(String percent);

  /// No description provided for @weeklyChangeDown.
  ///
  /// In fa, this message translates to:
  /// **'{percent} کمتر از هفته قبل'**
  String weeklyChangeDown(String percent);

  /// No description provided for @weeklyNoCompare.
  ///
  /// In fa, this message translates to:
  /// **'برای مقایسه، داده هفته قبل وجود ندارد.'**
  String get weeklyNoCompare;

  /// No description provided for @waterTitle.
  ///
  /// In fa, this message translates to:
  /// **'مصرف آب'**
  String get waterTitle;

  /// No description provided for @waterGlassesOf.
  ///
  /// In fa, this message translates to:
  /// **'{n} از {goal} لیوان'**
  String waterGlassesOf(String n, String goal);

  /// No description provided for @waterAddMl.
  ///
  /// In fa, this message translates to:
  /// **'+{ml} میلی‌لیتر'**
  String waterAddMl(String ml);

  /// No description provided for @waterSuggested.
  ///
  /// In fa, this message translates to:
  /// **'پیشنهاد تقریبی برای تو: {ml} میلی‌لیتر در روز'**
  String waterSuggested(String ml);

  /// No description provided for @waterUseSuggestion.
  ///
  /// In fa, this message translates to:
  /// **'تنظیم به‌عنوان هدف'**
  String get waterUseSuggestion;

  /// No description provided for @waterReminder.
  ///
  /// In fa, this message translates to:
  /// **'یادآوری آب'**
  String get waterReminder;

  /// No description provided for @waterReminderEvery.
  ///
  /// In fa, this message translates to:
  /// **'هر {minutes} دقیقه، از ۹ صبح تا ۹ شب'**
  String waterReminderEvery(String minutes);

  /// No description provided for @waterTodayLogs.
  ///
  /// In fa, this message translates to:
  /// **'ثبت‌های امروز'**
  String get waterTodayLogs;

  /// No description provided for @waterEmpty.
  ///
  /// In fa, this message translates to:
  /// **'امروز هنوز آبی ثبت نکرده‌ای.'**
  String get waterEmpty;

  /// No description provided for @homeStreak7.
  ///
  /// In fa, this message translates to:
  /// **'{days} روز متوالی'**
  String homeStreak7(String days);

  /// No description provided for @homeWater.
  ///
  /// In fa, this message translates to:
  /// **'آب امروز'**
  String get homeWater;

  /// No description provided for @homeChallenge.
  ///
  /// In fa, this message translates to:
  /// **'چالش فعال'**
  String get homeChallenge;

  /// No description provided for @homeNotifications.
  ///
  /// In fa, this message translates to:
  /// **'اعلان‌ها'**
  String get homeNotifications;

  /// No description provided for @levelLabel.
  ///
  /// In fa, this message translates to:
  /// **'سطح {level} · {title}'**
  String levelLabel(String level, String title);

  /// No description provided for @levelXp.
  ///
  /// In fa, this message translates to:
  /// **'{xp} از {next} XP'**
  String levelXp(String xp, String next);

  /// No description provided for @levelMax.
  ///
  /// In fa, this message translates to:
  /// **'بالاترین سطح'**
  String get levelMax;

  /// No description provided for @achievementsTitle.
  ///
  /// In fa, this message translates to:
  /// **'دستاوردها'**
  String get achievementsTitle;

  /// No description provided for @achievementsCount.
  ///
  /// In fa, this message translates to:
  /// **'{n} از {total} دستاورد'**
  String achievementsCount(String n, String total);

  /// No description provided for @leaderboardTitle.
  ///
  /// In fa, this message translates to:
  /// **'رتبه‌بندی'**
  String get leaderboardTitle;

  /// No description provided for @lbToday.
  ///
  /// In fa, this message translates to:
  /// **'امروز'**
  String get lbToday;

  /// No description provided for @lbWeek.
  ///
  /// In fa, this message translates to:
  /// **'این هفته'**
  String get lbWeek;

  /// No description provided for @lbMonth.
  ///
  /// In fa, this message translates to:
  /// **'این ماه'**
  String get lbMonth;

  /// No description provided for @lbYou.
  ///
  /// In fa, this message translates to:
  /// **'شما'**
  String get lbYou;

  /// No description provided for @lbHidden.
  ///
  /// In fa, this message translates to:
  /// **'نمایش شما در رتبه‌بندی خاموش است. دیگران نام و رتبه تو را نمی‌بینند.'**
  String get lbHidden;

  /// No description provided for @lbEmpty.
  ///
  /// In fa, this message translates to:
  /// **'هنوز کسی در این دوره رتبه ندارد.'**
  String get lbEmpty;

  /// No description provided for @lbPrivacy.
  ///
  /// In fa, this message translates to:
  /// **'فقط نام نمایشی، تصویر و سطح نشان داده می‌شود. رتبه‌بندی بر اساس قدم تأییدشده است.'**
  String get lbPrivacy;

  /// No description provided for @referralTitle.
  ///
  /// In fa, this message translates to:
  /// **'دعوت از دوستان'**
  String get referralTitle;

  /// No description provided for @referralBody.
  ///
  /// In fa, this message translates to:
  /// **'کد دعوتت را برای دوستانت بفرست. وقتی دوستت {steps} قدم تأییدشده بردارد، تو {mine} و او {theirs} امتیاز می‌گیرید.'**
  String referralBody(String steps, String mine, String theirs);

  /// No description provided for @referralShare.
  ///
  /// In fa, this message translates to:
  /// **'اشتراک‌گذاری'**
  String get referralShare;

  /// No description provided for @referralCopied.
  ///
  /// In fa, this message translates to:
  /// **'کد دعوت کپی شد.'**
  String get referralCopied;

  /// No description provided for @referralInvited.
  ///
  /// In fa, this message translates to:
  /// **'دعوت‌شده'**
  String get referralInvited;

  /// No description provided for @referralActive.
  ///
  /// In fa, this message translates to:
  /// **'فعال‌شده'**
  String get referralActive;

  /// No description provided for @referralPoints.
  ///
  /// In fa, this message translates to:
  /// **'امتیاز دعوت'**
  String get referralPoints;

  /// No description provided for @referralShareText.
  ///
  /// In fa, this message translates to:
  /// **'با گام‌یار راه برو و جایزه بگیر! هنگام ثبت‌نام کد دعوت من را وارد کن: {code}'**
  String referralShareText(String code);

  /// No description provided for @challengesTitle.
  ///
  /// In fa, this message translates to:
  /// **'چالش‌ها'**
  String get challengesTitle;

  /// No description provided for @challengesRunning.
  ///
  /// In fa, this message translates to:
  /// **'در جریان'**
  String get challengesRunning;

  /// No description provided for @challengesUpcoming.
  ///
  /// In fa, this message translates to:
  /// **'به‌زودی'**
  String get challengesUpcoming;

  /// No description provided for @challengesEnded.
  ///
  /// In fa, this message translates to:
  /// **'پایان‌یافته'**
  String get challengesEnded;

  /// No description provided for @challengeJoin.
  ///
  /// In fa, this message translates to:
  /// **'شرکت در چالش'**
  String get challengeJoin;

  /// No description provided for @challengeJoined.
  ///
  /// In fa, this message translates to:
  /// **'در حال انجام'**
  String get challengeJoined;

  /// No description provided for @challengeCompleted.
  ///
  /// In fa, this message translates to:
  /// **'تکمیل شد'**
  String get challengeCompleted;

  /// No description provided for @challengeReward.
  ///
  /// In fa, this message translates to:
  /// **'جایزه'**
  String get challengeReward;

  /// No description provided for @challengeParticipants.
  ///
  /// In fa, this message translates to:
  /// **'{n} شرکت‌کننده'**
  String challengeParticipants(String n);

  /// No description provided for @challengeEnds.
  ///
  /// In fa, this message translates to:
  /// **'پایان: {time}'**
  String challengeEnds(String time);

  /// No description provided for @challengeTop.
  ///
  /// In fa, this message translates to:
  /// **'پیشتازها'**
  String get challengeTop;

  /// No description provided for @challengeSponsored.
  ///
  /// In fa, this message translates to:
  /// **'اسپانسری'**
  String get challengeSponsored;

  /// No description provided for @challengesEmpty.
  ///
  /// In fa, this message translates to:
  /// **'فعلاً چالشی فعال نیست.'**
  String get challengesEmpty;

  /// No description provided for @challengeTarget.
  ///
  /// In fa, this message translates to:
  /// **'هدف: {target}'**
  String challengeTarget(String target);

  /// No description provided for @challengeProgressOnlyAfterJoin.
  ///
  /// In fa, this message translates to:
  /// **'فقط فعالیت‌های تأییدشده پس از پیوستن شمرده می‌شوند.'**
  String get challengeProgressOnlyAfterJoin;

  /// No description provided for @inboxTitle.
  ///
  /// In fa, this message translates to:
  /// **'اعلان‌ها'**
  String get inboxTitle;

  /// No description provided for @inboxEmpty.
  ///
  /// In fa, this message translates to:
  /// **'اعلانی نداری.'**
  String get inboxEmpty;

  /// No description provided for @inboxMarkRead.
  ///
  /// In fa, this message translates to:
  /// **'خواندن همه'**
  String get inboxMarkRead;

  /// No description provided for @profileAchievements.
  ///
  /// In fa, this message translates to:
  /// **'دستاوردها'**
  String get profileAchievements;

  /// No description provided for @profileReferral.
  ///
  /// In fa, this message translates to:
  /// **'دعوت از دوستان'**
  String get profileReferral;

  /// No description provided for @profileHealth.
  ///
  /// In fa, this message translates to:
  /// **'سلامت و آمار'**
  String get profileHealth;

  /// No description provided for @profileLeaderboard.
  ///
  /// In fa, this message translates to:
  /// **'رتبه‌بندی'**
  String get profileLeaderboard;

  /// No description provided for @profileWater.
  ///
  /// In fa, this message translates to:
  /// **'مصرف آب'**
  String get profileWater;

  /// No description provided for @unitSteps.
  ///
  /// In fa, this message translates to:
  /// **'قدم'**
  String get unitSteps;

  /// No description provided for @unitKm.
  ///
  /// In fa, this message translates to:
  /// **'کیلومتر'**
  String get unitKm;

  /// No description provided for @nearbyTitle.
  ///
  /// In fa, this message translates to:
  /// **'جایزه‌های اطراف'**
  String get nearbyTitle;

  /// No description provided for @nearbyList.
  ///
  /// In fa, this message translates to:
  /// **'فهرست'**
  String get nearbyList;

  /// No description provided for @nearbyMap.
  ///
  /// In fa, this message translates to:
  /// **'نقشه'**
  String get nearbyMap;

  /// No description provided for @nearbyEmpty.
  ///
  /// In fa, this message translates to:
  /// **'فعلاً در این اطراف پیشنهادی نیست. کمی جابه‌جا شو یا بعداً سر بزن.'**
  String get nearbyEmpty;

  /// No description provided for @nearbyLocationOff.
  ///
  /// In fa, this message translates to:
  /// **'موقعیت مکانی (GPS) گوشی خاموش است.'**
  String get nearbyLocationOff;

  /// No description provided for @nearbyLocationDenied.
  ///
  /// In fa, this message translates to:
  /// **'برای دیدن جایزه‌های اطراف، اجازه دسترسی به موقعیت لازم است.'**
  String get nearbyLocationDenied;

  /// No description provided for @nearbyEnableLocation.
  ///
  /// In fa, this message translates to:
  /// **'فعال کردن موقعیت'**
  String get nearbyEnableLocation;

  /// No description provided for @nearbyPrivacy.
  ///
  /// In fa, this message translates to:
  /// **'موقعیتت فقط برای همین جستجو استفاده می‌شود و ذخیره نمی‌شود.'**
  String get nearbyPrivacy;

  /// No description provided for @distanceM.
  ///
  /// In fa, this message translates to:
  /// **'{m} متر'**
  String distanceM(String m);

  /// No description provided for @distanceKm.
  ///
  /// In fa, this message translates to:
  /// **'{km} کیلومتر'**
  String distanceKm(String km);

  /// No description provided for @branchClosed.
  ///
  /// In fa, this message translates to:
  /// **'الان بسته است'**
  String get branchClosed;

  /// No description provided for @campaignStay.
  ///
  /// In fa, this message translates to:
  /// **'{min} دقیقه حضور'**
  String campaignStay(String min);

  /// No description provided for @campaignQr.
  ///
  /// In fa, this message translates to:
  /// **'اسکن QR صندوق'**
  String get campaignQr;

  /// No description provided for @campaignCoupon.
  ///
  /// In fa, this message translates to:
  /// **'کوپن هدیه: {title}'**
  String campaignCoupon(String title);

  /// No description provided for @campaignHow.
  ///
  /// In fa, this message translates to:
  /// **'چطور جایزه بگیرم؟'**
  String get campaignHow;

  /// No description provided for @campaignStep1.
  ///
  /// In fa, this message translates to:
  /// **'به یکی از شعبه‌های زیر برو.'**
  String get campaignStep1;

  /// No description provided for @campaignStep2.
  ///
  /// In fa, this message translates to:
  /// **'«شروع بازدید» را بزن و {min} دقیقه در شعبه بمان.'**
  String campaignStep2(String min);

  /// No description provided for @campaignStep3.
  ///
  /// In fa, this message translates to:
  /// **'QR روی صفحه صندوق را اسکن کن.'**
  String get campaignStep3;

  /// No description provided for @campaignStart.
  ///
  /// In fa, this message translates to:
  /// **'شروع بازدید'**
  String get campaignStart;

  /// No description provided for @campaignBranches.
  ///
  /// In fa, this message translates to:
  /// **'شعبه‌ها'**
  String get campaignBranches;

  /// No description provided for @campaignMine.
  ///
  /// In fa, this message translates to:
  /// **'{n} از {max} پاداش گرفته‌ای'**
  String campaignMine(String n, String max);

  /// No description provided for @reason_limit_reached.
  ///
  /// In fa, this message translates to:
  /// **'پاداش این کمپین را گرفته‌ای.'**
  String get reason_limit_reached;

  /// No description provided for @reason_cooldown.
  ///
  /// In fa, this message translates to:
  /// **'به‌تازگی از این شعبه پاداش گرفته‌ای.'**
  String get reason_cooldown;

  /// No description provided for @reason_campaign_exhausted.
  ///
  /// In fa, this message translates to:
  /// **'ظرفیت این کمپین تمام شده است.'**
  String get reason_campaign_exhausted;

  /// No description provided for @reason_campaign_ended.
  ///
  /// In fa, this message translates to:
  /// **'این کمپین تمام شده است.'**
  String get reason_campaign_ended;

  /// No description provided for @reason_teleport.
  ///
  /// In fa, this message translates to:
  /// **'موقعیتت به‌شکل غیرعادی جابه‌جا شد.'**
  String get reason_teleport;

  /// No description provided for @reason_mock_location.
  ///
  /// In fa, this message translates to:
  /// **'موقعیت شبیه‌سازی‌شده قابل قبول نیست.'**
  String get reason_mock_location;

  /// No description provided for @reason_other.
  ///
  /// In fa, this message translates to:
  /// **'شرایط پاداش کامل نشد.'**
  String get reason_other;

  /// No description provided for @visitTitle.
  ///
  /// In fa, this message translates to:
  /// **'بازدید'**
  String get visitTitle;

  /// No description provided for @visitInside.
  ///
  /// In fa, this message translates to:
  /// **'داخل محدوده شعبه هستی'**
  String get visitInside;

  /// No description provided for @visitOutside.
  ///
  /// In fa, this message translates to:
  /// **'بیرون از محدوده شعبه‌ای'**
  String get visitOutside;

  /// No description provided for @visitLocating.
  ///
  /// In fa, this message translates to:
  /// **'در حال پیدا کردن موقعیت…'**
  String get visitLocating;

  /// No description provided for @visitStay.
  ///
  /// In fa, this message translates to:
  /// **'{done} از {total}'**
  String visitStay(String done, String total);

  /// No description provided for @visitKeepOpen.
  ///
  /// In fa, this message translates to:
  /// **'این صفحه را باز نگه دار. زمان حضور را سرور اندازه می‌گیرد، نه گوشی.'**
  String get visitKeepOpen;

  /// No description provided for @visitScanQr.
  ///
  /// In fa, this message translates to:
  /// **'اسکن QR صندوق'**
  String get visitScanQr;

  /// No description provided for @visitQrDone.
  ///
  /// In fa, this message translates to:
  /// **'QR شعبه تأیید شد'**
  String get visitQrDone;

  /// No description provided for @visitQrHint.
  ///
  /// In fa, this message translates to:
  /// **'کد روی صفحه صندوق هر ۳۰ ثانیه عوض می‌شود. عکس آن کار نمی‌کند.'**
  String get visitQrHint;

  /// No description provided for @visitRewarded.
  ///
  /// In fa, this message translates to:
  /// **'بازدید تأیید شد!'**
  String get visitRewarded;

  /// No description provided for @visitRewardedBody.
  ///
  /// In fa, this message translates to:
  /// **'{n} امتیاز پس از دوره بررسی به کیف پولت اضافه می‌شود.'**
  String visitRewardedBody(String n);

  /// No description provided for @visitCouponReceived.
  ///
  /// In fa, this message translates to:
  /// **'کوپن هدیه به کوپن‌هایت اضافه شد'**
  String get visitCouponReceived;

  /// No description provided for @visitRejected.
  ///
  /// In fa, this message translates to:
  /// **'بازدید تأیید نشد'**
  String get visitRejected;

  /// No description provided for @visitExpired.
  ///
  /// In fa, this message translates to:
  /// **'این بازدید منقضی شد'**
  String get visitExpired;

  /// No description provided for @visitBack.
  ///
  /// In fa, this message translates to:
  /// **'بازگشت'**
  String get visitBack;

  /// No description provided for @qrScanTitle.
  ///
  /// In fa, this message translates to:
  /// **'اسکن QR شعبه'**
  String get qrScanTitle;

  /// No description provided for @qrScanHint.
  ///
  /// In fa, this message translates to:
  /// **'کد روی صفحه صندوق را داخل کادر بگیر.'**
  String get qrScanHint;

  /// No description provided for @couponsTitle.
  ///
  /// In fa, this message translates to:
  /// **'کوپن‌ها'**
  String get couponsTitle;

  /// No description provided for @couponsMine.
  ///
  /// In fa, this message translates to:
  /// **'کوپن‌های من'**
  String get couponsMine;

  /// No description provided for @couponsAvailable.
  ///
  /// In fa, this message translates to:
  /// **'دریافت با امتیاز'**
  String get couponsAvailable;

  /// No description provided for @couponsEmpty.
  ///
  /// In fa, this message translates to:
  /// **'هنوز کوپنی نداری. با بازدید از شعبه‌های اسپانسر کوپن هدیه بگیر.'**
  String get couponsEmpty;

  /// No description provided for @couponsAvailableEmpty.
  ///
  /// In fa, this message translates to:
  /// **'فعلاً کوپنی برای دریافت نیست.'**
  String get couponsAvailableEmpty;

  /// No description provided for @couponCode.
  ///
  /// In fa, this message translates to:
  /// **'کد کوپن'**
  String get couponCode;

  /// No description provided for @couponShowCashier.
  ///
  /// In fa, this message translates to:
  /// **'این کد را به صندوق‌دار نشان بده.'**
  String get couponShowCashier;

  /// No description provided for @couponExpires.
  ///
  /// In fa, this message translates to:
  /// **'اعتبار تا {date}'**
  String couponExpires(String date);

  /// No description provided for @couponUsedAt.
  ///
  /// In fa, this message translates to:
  /// **'استفاده‌شده در {date}'**
  String couponUsedAt(String date);

  /// No description provided for @couponClaim.
  ///
  /// In fa, this message translates to:
  /// **'دریافت با {n} امتیاز'**
  String couponClaim(String n);

  /// No description provided for @couponClaimFree.
  ///
  /// In fa, this message translates to:
  /// **'دریافت رایگان'**
  String get couponClaimFree;

  /// No description provided for @couponClaimConfirm.
  ///
  /// In fa, this message translates to:
  /// **'{n} امتیاز از کیف پولت کم می‌شود. ادامه می‌دهی؟'**
  String couponClaimConfirm(String n);

  /// No description provided for @couponClaimed.
  ///
  /// In fa, this message translates to:
  /// **'کوپن به کوپن‌هایت اضافه شد.'**
  String get couponClaimed;

  /// No description provided for @couponRemaining.
  ///
  /// In fa, this message translates to:
  /// **'{n} عدد باقی مانده'**
  String couponRemaining(String n);

  /// No description provided for @couponTerms.
  ///
  /// In fa, this message translates to:
  /// **'شرایط استفاده'**
  String get couponTerms;

  /// No description provided for @couponOnlineCode.
  ///
  /// In fa, this message translates to:
  /// **'کد خرید آنلاین: {code}'**
  String couponOnlineCode(String code);

  /// No description provided for @couponCopied.
  ///
  /// In fa, this message translates to:
  /// **'کد کپی شد.'**
  String get couponCopied;

  /// No description provided for @permCameraTitle.
  ///
  /// In fa, this message translates to:
  /// **'دسترسی به دوربین'**
  String get permCameraTitle;

  /// No description provided for @permCameraBody.
  ///
  /// In fa, this message translates to:
  /// **'برای اسکن QR صندوق شعبه. دوربین فقط در همین صفحه روشن می‌شود و تصویری ذخیره نمی‌شود.'**
  String get permCameraBody;

  /// No description provided for @rewardsNearby.
  ///
  /// In fa, this message translates to:
  /// **'جایزه‌های اطراف'**
  String get rewardsNearby;

  /// No description provided for @rewardsCoupons.
  ///
  /// In fa, this message translates to:
  /// **'کوپن‌ها'**
  String get rewardsCoupons;

  /// No description provided for @profileCoupons.
  ///
  /// In fa, this message translates to:
  /// **'کوپن‌های من'**
  String get profileCoupons;

  /// No description provided for @visitOfTotal.
  ///
  /// In fa, this message translates to:
  /// **'از {total}'**
  String visitOfTotal(String total);

  /// No description provided for @adLabel.
  ///
  /// In fa, this message translates to:
  /// **'تبلیغ'**
  String get adLabel;

  /// No description provided for @rewardedCardTitle.
  ///
  /// In fa, this message translates to:
  /// **'تبلیغ ببین، امتیاز بگیر'**
  String get rewardedCardTitle;

  /// No description provided for @rewardedCardBody.
  ///
  /// In fa, this message translates to:
  /// **'امروز {n} بار دیگر'**
  String rewardedCardBody(String n);

  /// No description provided for @rewardedTitle.
  ///
  /// In fa, this message translates to:
  /// **'تبلیغ جایزه‌دار'**
  String get rewardedTitle;

  /// No description provided for @rewardedWait.
  ///
  /// In fa, this message translates to:
  /// **'{s} ثانیه تا دریافت امتیاز'**
  String rewardedWait(String s);

  /// No description provided for @rewardedClaim.
  ///
  /// In fa, this message translates to:
  /// **'دریافت {n} امتیاز'**
  String rewardedClaim(String n);

  /// No description provided for @rewardedDone.
  ///
  /// In fa, this message translates to:
  /// **'امتیاز ثبت شد'**
  String get rewardedDone;

  /// No description provided for @rewardedDoneBody.
  ///
  /// In fa, this message translates to:
  /// **'{n} امتیاز پس از دوره بررسی به کیف پولت اضافه می‌شود.'**
  String rewardedDoneBody(String n);

  /// No description provided for @rewardedFailed.
  ///
  /// In fa, this message translates to:
  /// **'این بار امتیازی ثبت نشد.'**
  String get rewardedFailed;

  /// No description provided for @rewardedLeaveHint.
  ///
  /// In fa, this message translates to:
  /// **'اگر قبل از پایان زمان خارج شوی، امتیازی ثبت نمی‌شود.'**
  String get rewardedLeaveHint;

  /// No description provided for @storeTitle.
  ///
  /// In fa, this message translates to:
  /// **'فروشگاه'**
  String get storeTitle;

  /// No description provided for @storeAll.
  ///
  /// In fa, this message translates to:
  /// **'همه'**
  String get storeAll;

  /// No description provided for @storeSearch.
  ///
  /// In fa, this message translates to:
  /// **'جستجو در فروشگاه'**
  String get storeSearch;

  /// No description provided for @storeEmpty.
  ///
  /// In fa, this message translates to:
  /// **'کالایی پیدا نشد.'**
  String get storeEmpty;

  /// No description provided for @storeDisabled.
  ///
  /// In fa, this message translates to:
  /// **'فروشگاه به‌زودی باز می‌شود.'**
  String get storeDisabled;

  /// No description provided for @storeOutOfStock.
  ///
  /// In fa, this message translates to:
  /// **'ناموجود'**
  String get storeOutOfStock;

  /// No description provided for @storeFewLeft.
  ///
  /// In fa, this message translates to:
  /// **'فقط {n} عدد باقی مانده'**
  String storeFewLeft(String n);

  /// No description provided for @storeMinLevel.
  ///
  /// In fa, this message translates to:
  /// **'از سطح {n}'**
  String storeMinLevel(String n);

  /// No description provided for @storeMaxPerUser.
  ///
  /// In fa, this message translates to:
  /// **'حداکثر {n} عدد برای هر نفر'**
  String storeMaxPerUser(String n);

  /// No description provided for @storeBuy.
  ///
  /// In fa, this message translates to:
  /// **'خرید'**
  String get storeBuy;

  /// No description provided for @storeYourBalance.
  ///
  /// In fa, this message translates to:
  /// **'موجودی تو: {n} امتیاز'**
  String storeYourBalance(String n);

  /// No description provided for @storeNeedMore.
  ///
  /// In fa, this message translates to:
  /// **'{n} امتیاز دیگر لازم داری'**
  String storeNeedMore(String n);

  /// No description provided for @storeSortFeatured.
  ///
  /// In fa, this message translates to:
  /// **'پیشنهادی'**
  String get storeSortFeatured;

  /// No description provided for @storeSortCheap.
  ///
  /// In fa, this message translates to:
  /// **'ارزان‌ترین'**
  String get storeSortCheap;

  /// No description provided for @storeSortExpensive.
  ///
  /// In fa, this message translates to:
  /// **'گران‌ترین'**
  String get storeSortExpensive;

  /// No description provided for @storeSortNew.
  ///
  /// In fa, this message translates to:
  /// **'جدیدترین'**
  String get storeSortNew;

  /// No description provided for @checkoutTitle.
  ///
  /// In fa, this message translates to:
  /// **'تکمیل خرید'**
  String get checkoutTitle;

  /// No description provided for @checkoutQuantity.
  ///
  /// In fa, this message translates to:
  /// **'تعداد'**
  String get checkoutQuantity;

  /// No description provided for @checkoutAddress.
  ///
  /// In fa, this message translates to:
  /// **'نشانی ارسال'**
  String get checkoutAddress;

  /// No description provided for @checkoutAddAddress.
  ///
  /// In fa, this message translates to:
  /// **'افزودن نشانی'**
  String get checkoutAddAddress;

  /// No description provided for @checkoutTotal.
  ///
  /// In fa, this message translates to:
  /// **'جمع'**
  String get checkoutTotal;

  /// No description provided for @checkoutAfter.
  ///
  /// In fa, this message translates to:
  /// **'موجودی پس از خرید: {n}'**
  String checkoutAfter(String n);

  /// No description provided for @checkoutConfirm.
  ///
  /// In fa, this message translates to:
  /// **'پرداخت با {n} امتیاز'**
  String checkoutConfirm(String n);

  /// No description provided for @checkoutInstant.
  ///
  /// In fa, this message translates to:
  /// **'بلافاصله پس از خرید در «سفارش‌های من» تحویل داده می‌شود.'**
  String get checkoutInstant;

  /// No description provided for @checkoutNote.
  ///
  /// In fa, this message translates to:
  /// **'توضیح (اختیاری)'**
  String get checkoutNote;

  /// No description provided for @ordersTitle.
  ///
  /// In fa, this message translates to:
  /// **'سفارش‌های من'**
  String get ordersTitle;

  /// No description provided for @ordersEmpty.
  ///
  /// In fa, this message translates to:
  /// **'هنوز سفارشی نداری.'**
  String get ordersEmpty;

  /// No description provided for @orderItems.
  ///
  /// In fa, this message translates to:
  /// **'{n} قلم'**
  String orderItems(String n);

  /// No description provided for @orderTracking.
  ///
  /// In fa, this message translates to:
  /// **'کد رهگیری: {code}'**
  String orderTracking(String code);

  /// No description provided for @orderCodes.
  ///
  /// In fa, this message translates to:
  /// **'کدهای تو'**
  String get orderCodes;

  /// No description provided for @orderCodeCopied.
  ///
  /// In fa, this message translates to:
  /// **'کد کپی شد.'**
  String get orderCodeCopied;

  /// No description provided for @orderCouponLink.
  ///
  /// In fa, this message translates to:
  /// **'مشاهده در کوپن‌ها'**
  String get orderCouponLink;

  /// No description provided for @orderCancel.
  ///
  /// In fa, this message translates to:
  /// **'لغو سفارش و بازگشت امتیاز'**
  String get orderCancel;

  /// No description provided for @orderCancelConfirm.
  ///
  /// In fa, this message translates to:
  /// **'سفارش لغو و امتیاز به کیف پولت برمی‌گردد. ادامه می‌دهی؟'**
  String get orderCancelConfirm;

  /// No description provided for @orderCancelled.
  ///
  /// In fa, this message translates to:
  /// **'سفارش لغو شد و امتیاز برگشت.'**
  String get orderCancelled;

  /// No description provided for @orderShipTo.
  ///
  /// In fa, this message translates to:
  /// **'ارسال به'**
  String get orderShipTo;

  /// No description provided for @orderTimeline.
  ///
  /// In fa, this message translates to:
  /// **'وضعیت سفارش'**
  String get orderTimeline;

  /// No description provided for @addressesTitle.
  ///
  /// In fa, this message translates to:
  /// **'نشانی‌ها'**
  String get addressesTitle;

  /// No description provided for @addressesEmpty.
  ///
  /// In fa, this message translates to:
  /// **'هنوز نشانی ثبت نکرده‌ای.'**
  String get addressesEmpty;

  /// No description provided for @addressNew.
  ///
  /// In fa, this message translates to:
  /// **'نشانی جدید'**
  String get addressNew;

  /// No description provided for @addressEdit.
  ///
  /// In fa, this message translates to:
  /// **'ویرایش نشانی'**
  String get addressEdit;

  /// No description provided for @addressTitleField.
  ///
  /// In fa, this message translates to:
  /// **'عنوان (مثلاً خانه)'**
  String get addressTitleField;

  /// No description provided for @addressRecipient.
  ///
  /// In fa, this message translates to:
  /// **'نام گیرنده'**
  String get addressRecipient;

  /// No description provided for @addressPhone.
  ///
  /// In fa, this message translates to:
  /// **'شماره تماس'**
  String get addressPhone;

  /// No description provided for @addressProvince.
  ///
  /// In fa, this message translates to:
  /// **'استان'**
  String get addressProvince;

  /// No description provided for @addressCity.
  ///
  /// In fa, this message translates to:
  /// **'شهر'**
  String get addressCity;

  /// No description provided for @addressLine.
  ///
  /// In fa, this message translates to:
  /// **'نشانی کامل'**
  String get addressLine;

  /// No description provided for @addressPostalCode.
  ///
  /// In fa, this message translates to:
  /// **'کد پستی ۱۰ رقمی'**
  String get addressPostalCode;

  /// No description provided for @addressDefault.
  ///
  /// In fa, this message translates to:
  /// **'نشانی پیش‌فرض'**
  String get addressDefault;

  /// No description provided for @addressDelete.
  ///
  /// In fa, this message translates to:
  /// **'حذف نشانی'**
  String get addressDelete;

  /// No description provided for @addressInvalidPhone.
  ///
  /// In fa, this message translates to:
  /// **'شماره تماس معتبر نیست.'**
  String get addressInvalidPhone;

  /// No description provided for @addressInvalidPostal.
  ///
  /// In fa, this message translates to:
  /// **'کد پستی باید ۱۰ رقم باشد.'**
  String get addressInvalidPostal;

  /// No description provided for @fieldRequired.
  ///
  /// In fa, this message translates to:
  /// **'این فیلد لازم است.'**
  String get fieldRequired;

  /// No description provided for @profileOrders.
  ///
  /// In fa, this message translates to:
  /// **'سفارش‌های من'**
  String get profileOrders;

  /// No description provided for @profileAddresses.
  ///
  /// In fa, this message translates to:
  /// **'نشانی‌ها'**
  String get profileAddresses;

  /// No description provided for @supportTitle.
  ///
  /// In fa, this message translates to:
  /// **'پشتیبانی'**
  String get supportTitle;

  /// No description provided for @supportEmpty.
  ///
  /// In fa, this message translates to:
  /// **'درخواستی ثبت نکرده‌ای. اگر مشکلی داری، اینجا بنویس.'**
  String get supportEmpty;

  /// No description provided for @supportNew.
  ///
  /// In fa, this message translates to:
  /// **'درخواست جدید'**
  String get supportNew;

  /// No description provided for @supportFaqHint.
  ///
  /// In fa, this message translates to:
  /// **'شاید جوابت در سوالات متداول باشد.'**
  String get supportFaqHint;

  /// No description provided for @supportFaqOpen.
  ///
  /// In fa, this message translates to:
  /// **'سوالات متداول'**
  String get supportFaqOpen;

  /// No description provided for @supportCategory.
  ///
  /// In fa, this message translates to:
  /// **'موضوع کلی'**
  String get supportCategory;

  /// No description provided for @supportSubject.
  ///
  /// In fa, this message translates to:
  /// **'عنوان'**
  String get supportSubject;

  /// No description provided for @supportBody.
  ///
  /// In fa, this message translates to:
  /// **'شرح مشکل'**
  String get supportBody;

  /// No description provided for @supportBodyHint.
  ///
  /// In fa, this message translates to:
  /// **'هرچه دقیق‌تر بنویسی (تاریخ، مقدار، شماره سفارش)، سریع‌تر پاسخ می‌گیری.'**
  String get supportBodyHint;

  /// No description provided for @supportSend.
  ///
  /// In fa, this message translates to:
  /// **'ارسال'**
  String get supportSend;

  /// No description provided for @supportReplyHint.
  ///
  /// In fa, this message translates to:
  /// **'پاسخ تو…'**
  String get supportReplyHint;

  /// No description provided for @supportClose.
  ///
  /// In fa, this message translates to:
  /// **'بستن درخواست'**
  String get supportClose;

  /// No description provided for @supportClosed.
  ///
  /// In fa, this message translates to:
  /// **'این درخواست بسته شده است.'**
  String get supportClosed;

  /// No description provided for @supportMe.
  ///
  /// In fa, this message translates to:
  /// **'تو'**
  String get supportMe;

  /// No description provided for @supportAgent.
  ///
  /// In fa, this message translates to:
  /// **'پشتیبانی گام‌یار'**
  String get supportAgent;

  /// No description provided for @supportTooShort.
  ///
  /// In fa, this message translates to:
  /// **'کمی بیشتر توضیح بده (حداقل ۱۰ حرف).'**
  String get supportTooShort;

  /// No description provided for @profileSupport.
  ///
  /// In fa, this message translates to:
  /// **'پشتیبانی'**
  String get profileSupport;

  /// No description provided for @avatarChange.
  ///
  /// In fa, this message translates to:
  /// **'تغییر عکس'**
  String get avatarChange;

  /// No description provided for @avatarFromGallery.
  ///
  /// In fa, this message translates to:
  /// **'انتخاب از گالری'**
  String get avatarFromGallery;

  /// No description provided for @avatarFromCamera.
  ///
  /// In fa, this message translates to:
  /// **'گرفتن عکس'**
  String get avatarFromCamera;

  /// No description provided for @avatarRemove.
  ///
  /// In fa, this message translates to:
  /// **'حذف عکس'**
  String get avatarRemove;

  /// No description provided for @avatarUpdated.
  ///
  /// In fa, this message translates to:
  /// **'عکس پروفایل به‌روز شد.'**
  String get avatarUpdated;

  /// No description provided for @avatarRemoved.
  ///
  /// In fa, this message translates to:
  /// **'عکس پروفایل حذف شد.'**
  String get avatarRemoved;

  /// No description provided for @productGalleryLabel.
  ///
  /// In fa, this message translates to:
  /// **'تصویر {index} از {count}'**
  String productGalleryLabel(String index, String count);

  /// No description provided for @gateTitle.
  ///
  /// In fa, this message translates to:
  /// **'چند دسترسی ضروری'**
  String get gateTitle;

  /// No description provided for @gateBody.
  ///
  /// In fa, this message translates to:
  /// **'گام‌یار قدم‌هایت را در پس‌زمینه می‌شمارد و بابت آن امتیاز می‌دهد. بدون این دسترسی‌ها هیچ قدمی ثبت نمی‌شود و امتیازی هم به تو نمی‌رسد.'**
  String get gateBody;

  /// No description provided for @gateActivityWhy.
  ///
  /// In fa, this message translates to:
  /// **'برای شمردن قدم‌ها با حسگر گوشی. هیچ داده‌ای از مکان تو خوانده نمی‌شود.'**
  String get gateActivityWhy;

  /// No description provided for @gateNotificationsWhy.
  ///
  /// In fa, this message translates to:
  /// **'برای اعلان پیاده‌روی در حال ثبت، یادآور آب و خبر پاداش‌ها. بدون آن اندروید ثبت پس‌زمینه را متوقف می‌کند.'**
  String get gateNotificationsWhy;

  /// No description provided for @gateBatteryTitle.
  ///
  /// In fa, this message translates to:
  /// **'اجرای بدون محدودیت باتری'**
  String get gateBatteryTitle;

  /// No description provided for @gateBatteryWhy.
  ///
  /// In fa, this message translates to:
  /// **'اندروید برای صرفه‌جویی، برنامه‌های پس‌زمینه را می‌بندد و قدم‌ها گم می‌شوند. مصرف باتری گام‌یار بسیار کم است.'**
  String get gateBatteryWhy;

  /// No description provided for @gateAutostartTitle.
  ///
  /// In fa, this message translates to:
  /// **'اجازه اجرای خودکار در گوشی {brand}'**
  String gateAutostartTitle(String brand);

  /// No description provided for @gateAutostartOpen.
  ///
  /// In fa, this message translates to:
  /// **'باز کردن تنظیمات'**
  String get gateAutostartOpen;

  /// No description provided for @gateAutostartDone.
  ///
  /// In fa, this message translates to:
  /// **'فعال کردم'**
  String get gateAutostartDone;

  /// No description provided for @gateRequiredHint.
  ///
  /// In fa, this message translates to:
  /// **'برای ادامه، همه موارد بالا باید فعال شوند.'**
  String get gateRequiredHint;

  /// No description provided for @gateContinue.
  ///
  /// In fa, this message translates to:
  /// **'ورود به گام‌یار'**
  String get gateContinue;

  /// No description provided for @oemXiaomi.
  ///
  /// In fa, this message translates to:
  /// **'در صفحه‌ای که باز می‌شود «Autostart» را برای گام‌یار روشن کن. سپس در تنظیمات باتری برنامه، «No restrictions» را انتخاب کن.'**
  String get oemXiaomi;

  /// No description provided for @oemHuawei.
  ///
  /// In fa, this message translates to:
  /// **'در «App launch»، گام‌یار را روی «Manage manually» بگذار و هر سه گزینه (Auto-launch، Secondary launch، Run in background) را روشن کن.'**
  String get oemHuawei;

  /// No description provided for @oemOppo.
  ///
  /// In fa, this message translates to:
  /// **'در «Auto launch» / «Startup manager» گام‌یار را روشن کن و در تنظیمات باتری، «Allow background activity» را فعال کن.'**
  String get oemOppo;

  /// No description provided for @oemVivo.
  ///
  /// In fa, this message translates to:
  /// **'در «Background power consumption» یا «Autostart»، گام‌یار را مجاز کن تا در پس‌زمینه بسته نشود.'**
  String get oemVivo;

  /// No description provided for @oemOnePlus.
  ///
  /// In fa, this message translates to:
  /// **'در «Battery optimization» گام‌یار را روی «Don\'t optimize» بگذار و «Auto launch» را روشن کن.'**
  String get oemOnePlus;

  /// No description provided for @oemSamsung.
  ///
  /// In fa, this message translates to:
  /// **'در «Battery» مطمئن شو گام‌یار در فهرست «Sleeping apps» و «Deep sleeping apps» نیست و آن را به «Never sleeping apps» اضافه کن.'**
  String get oemSamsung;

  /// No description provided for @checkoutWithPoints.
  ///
  /// In fa, this message translates to:
  /// **'با امتیاز'**
  String get checkoutWithPoints;

  /// No description provided for @checkoutWithMoney.
  ///
  /// In fa, this message translates to:
  /// **'پرداخت ریالی'**
  String get checkoutWithMoney;

  /// No description provided for @checkoutMoneyHint.
  ///
  /// In fa, this message translates to:
  /// **'پرداخت در درگاه امن زرین‌پال و در مرورگر گوشی انجام می‌شود. کالا تا ۲۰ دقیقه برایت نگه داشته می‌شود.'**
  String get checkoutMoneyHint;

  /// No description provided for @checkoutPayMoney.
  ///
  /// In fa, this message translates to:
  /// **'پرداخت {amount}'**
  String checkoutPayMoney(String amount);

  /// No description provided for @orderAwaitingPayment.
  ///
  /// In fa, this message translates to:
  /// **'در انتظار پرداخت'**
  String get orderAwaitingPayment;

  /// No description provided for @orderPayNow.
  ///
  /// In fa, this message translates to:
  /// **'ادامه پرداخت'**
  String get orderPayNow;

  /// No description provided for @orderPaymentExpired.
  ///
  /// In fa, this message translates to:
  /// **'مهلت پرداخت تمام شده است. اگر مبلغی کسر شده، تا چند دقیقه دیگر وضعیت سفارش به‌روز می‌شود.'**
  String get orderPaymentExpired;

  /// No description provided for @orderPaidRef.
  ///
  /// In fa, this message translates to:
  /// **'پرداخت شد — کد پیگیری {ref}'**
  String orderPaidRef(String ref);

  /// No description provided for @orderPaymentFailed.
  ///
  /// In fa, this message translates to:
  /// **'پرداخت انجام نشد و سفارش لغو شد.'**
  String get orderPaymentFailed;

  /// No description provided for @profileTheme.
  ///
  /// In fa, this message translates to:
  /// **'ظاهر برنامه'**
  String get profileTheme;

  /// No description provided for @themeSystem.
  ///
  /// In fa, this message translates to:
  /// **'مطابق گوشی'**
  String get themeSystem;

  /// No description provided for @themeLight.
  ///
  /// In fa, this message translates to:
  /// **'روشن'**
  String get themeLight;

  /// No description provided for @themeDark.
  ///
  /// In fa, this message translates to:
  /// **'تیره'**
  String get themeDark;

  /// No description provided for @freezeTitle.
  ///
  /// In fa, this message translates to:
  /// **'محافظ زنجیره'**
  String get freezeTitle;

  /// No description provided for @freezeBody.
  ///
  /// In fa, this message translates to:
  /// **'اگر یک روز به هدفت نرسی، محافظ به‌طور خودکار همان روز را پوشش می‌دهد و زنجیره‌ات نمی‌شکند. فقط برای دو روز گذشته کار می‌کند.'**
  String get freezeBody;

  /// No description provided for @freezeBuy.
  ///
  /// In fa, this message translates to:
  /// **'خرید محافظ با {price} امتیاز'**
  String freezeBuy(String price);

  /// No description provided for @freezeFull.
  ///
  /// In fa, this message translates to:
  /// **'ظرفیت محافظ‌هایت پر است.'**
  String get freezeFull;

  /// No description provided for @freezeBought.
  ///
  /// In fa, this message translates to:
  /// **'محافظ زنجیره خریداری شد.'**
  String get freezeBought;

  /// No description provided for @freezeOwned.
  ///
  /// In fa, this message translates to:
  /// **'{n} محافظ زنجیره'**
  String freezeOwned(String n);

  /// No description provided for @streakLongest.
  ///
  /// In fa, this message translates to:
  /// **'طولانی‌ترین زنجیره: {n} روز'**
  String streakLongest(String n);

  /// No description provided for @questsTitle.
  ///
  /// In fa, this message translates to:
  /// **'مأموریت‌ها'**
  String get questsTitle;

  /// No description provided for @questsEmpty.
  ///
  /// In fa, this message translates to:
  /// **'فعلاً مأموریتی تعریف نشده است.'**
  String get questsEmpty;

  /// No description provided for @questsDaily.
  ///
  /// In fa, this message translates to:
  /// **'امروز'**
  String get questsDaily;

  /// No description provided for @questsDailyHint.
  ///
  /// In fa, this message translates to:
  /// **'هر شب نیمه‌شب تازه می‌شوند'**
  String get questsDailyHint;

  /// No description provided for @questsWeekly.
  ///
  /// In fa, this message translates to:
  /// **'این هفته'**
  String get questsWeekly;

  /// No description provided for @questsWeeklyHint.
  ///
  /// In fa, this message translates to:
  /// **'از شنبه تا جمعه'**
  String get questsWeeklyHint;

  /// No description provided for @questClaim.
  ///
  /// In fa, this message translates to:
  /// **'دریافت'**
  String get questClaim;

  /// No description provided for @questDone.
  ///
  /// In fa, this message translates to:
  /// **'دریافت شد'**
  String get questDone;

  /// No description provided for @questClaimed.
  ///
  /// In fa, this message translates to:
  /// **'{points} امتیاز به کیف پولت اضافه شد (پس از بررسی آزاد می‌شود).'**
  String questClaimed(String points);

  /// No description provided for @questsReady.
  ///
  /// In fa, this message translates to:
  /// **'{n} جایزه آماده'**
  String questsReady(String n);

  /// No description provided for @questsProgress.
  ///
  /// In fa, this message translates to:
  /// **'{done} از {total}'**
  String questsProgress(String done, String total);

  /// No description provided for @friendsTitle.
  ///
  /// In fa, this message translates to:
  /// **'دوستان'**
  String get friendsTitle;

  /// No description provided for @profileFriends.
  ///
  /// In fa, this message translates to:
  /// **'دوستان و رقابت دوستانه'**
  String get profileFriends;

  /// No description provided for @friendMyCode.
  ///
  /// In fa, this message translates to:
  /// **'کد دوستی تو'**
  String get friendMyCode;

  /// No description provided for @friendShare.
  ///
  /// In fa, this message translates to:
  /// **'ارسال کد'**
  String get friendShare;

  /// No description provided for @friendShareText.
  ///
  /// In fa, this message translates to:
  /// **'در گام‌یار با من دوست شو و با هم قدم بزنیم! کد دوستی من: {code}'**
  String friendShareText(String code);

  /// No description provided for @friendAddLabel.
  ///
  /// In fa, this message translates to:
  /// **'کد دوستت'**
  String get friendAddLabel;

  /// No description provided for @friendAdd.
  ///
  /// In fa, this message translates to:
  /// **'افزودن'**
  String get friendAdd;

  /// No description provided for @friendRequested.
  ///
  /// In fa, this message translates to:
  /// **'درخواست دوستی فرستاده شد.'**
  String get friendRequested;

  /// No description provided for @friendIncoming.
  ///
  /// In fa, this message translates to:
  /// **'درخواست‌های دوستی'**
  String get friendIncoming;

  /// No description provided for @friendOutgoing.
  ///
  /// In fa, this message translates to:
  /// **'در انتظار تأیید'**
  String get friendOutgoing;

  /// No description provided for @friendWaiting.
  ///
  /// In fa, this message translates to:
  /// **'هنوز تأیید نکرده'**
  String get friendWaiting;

  /// No description provided for @friendAccept.
  ///
  /// In fa, this message translates to:
  /// **'پذیرفتن'**
  String get friendAccept;

  /// No description provided for @friendDecline.
  ///
  /// In fa, this message translates to:
  /// **'رد کردن'**
  String get friendDecline;

  /// No description provided for @friendAccepted.
  ///
  /// In fa, this message translates to:
  /// **'حالا با هم دوستید.'**
  String get friendAccepted;

  /// No description provided for @friendWeekRanking.
  ///
  /// In fa, this message translates to:
  /// **'قدم‌های این هفته'**
  String get friendWeekRanking;

  /// No description provided for @friendWeekSteps.
  ///
  /// In fa, this message translates to:
  /// **'{n} قدم این هفته'**
  String friendWeekSteps(String n);

  /// No description provided for @friendEmpty.
  ///
  /// In fa, this message translates to:
  /// **'هنوز دوستی اضافه نکرده‌ای. کدت را بفرست یا کد دوستت را وارد کن.'**
  String get friendEmpty;

  /// No description provided for @friendRemoveConfirm.
  ///
  /// In fa, this message translates to:
  /// **'{name} از فهرست دوستانت حذف شود؟'**
  String friendRemoveConfirm(String name);

  /// No description provided for @raceTitle.
  ///
  /// In fa, this message translates to:
  /// **'رقابت‌های دوستانه'**
  String get raceTitle;

  /// No description provided for @raceNew.
  ///
  /// In fa, this message translates to:
  /// **'رقابت دوستانه'**
  String get raceNew;

  /// No description provided for @raceHint.
  ///
  /// In fa, this message translates to:
  /// **'از فردا شروع می‌شود و فقط قدم‌های تأییدشده حساب می‌شوند. این رقابت امتیاز ندارد؛ فقط افتخار!'**
  String get raceHint;

  /// No description provided for @raceName.
  ///
  /// In fa, this message translates to:
  /// **'نام رقابت'**
  String get raceName;

  /// No description provided for @raceNameHint.
  ///
  /// In fa, this message translates to:
  /// **'مثلاً «هفته پرقدم»'**
  String get raceNameHint;

  /// No description provided for @raceDays.
  ///
  /// In fa, this message translates to:
  /// **'{n} روز'**
  String raceDays(String n);

  /// No description provided for @raceCreate.
  ///
  /// In fa, this message translates to:
  /// **'ساختن و دعوت'**
  String get raceCreate;

  /// No description provided for @raceMembers.
  ///
  /// In fa, this message translates to:
  /// **'{n} نفر'**
  String raceMembers(String n);

  /// No description provided for @raceInvited.
  ///
  /// In fa, this message translates to:
  /// **'دعوت شده‌ای'**
  String get raceInvited;

  /// No description provided for @raceUpcoming.
  ///
  /// In fa, this message translates to:
  /// **'شروع از فردا'**
  String get raceUpcoming;

  /// No description provided for @raceRunning.
  ///
  /// In fa, this message translates to:
  /// **'در جریان'**
  String get raceRunning;

  /// No description provided for @raceFinished.
  ///
  /// In fa, this message translates to:
  /// **'تمام‌شده'**
  String get raceFinished;

  /// No description provided for @raceBy.
  ///
  /// In fa, this message translates to:
  /// **'ساخته‌شده توسط {name}'**
  String raceBy(String name);

  /// No description provided for @raceInviteBody.
  ///
  /// In fa, this message translates to:
  /// **'به این رقابت دعوت شده‌ای. شرکت می‌کنی؟'**
  String get raceInviteBody;

  /// No description provided for @raceJoin.
  ///
  /// In fa, this message translates to:
  /// **'شرکت می‌کنم'**
  String get raceJoin;

  /// No description provided for @raceLeave.
  ///
  /// In fa, this message translates to:
  /// **'خروج از رقابت'**
  String get raceLeave;

  /// No description provided for @raceStartsTomorrow.
  ///
  /// In fa, this message translates to:
  /// **'رقابت از فردا شروع می‌شود.'**
  String get raceStartsTomorrow;

  /// No description provided for @raceSteps.
  ///
  /// In fa, this message translates to:
  /// **'{n} قدم'**
  String raceSteps(String n);

  /// No description provided for @racePending.
  ///
  /// In fa, this message translates to:
  /// **'در انتظار پاسخ: {names}'**
  String racePending(String names);

  /// No description provided for @raceFairPlay.
  ///
  /// In fa, this message translates to:
  /// **'فقط قدم‌های تأییدشده توسط سیستم ضد تقلب شمرده می‌شوند.'**
  String get raceFairPlay;

  /// No description provided for @friendAddHint.
  ///
  /// In fa, this message translates to:
  /// **'کد دعوت دوستت'**
  String get friendAddHint;

  /// No description provided for @cashoutTitle.
  ///
  /// In fa, this message translates to:
  /// **'برداشت نقدی'**
  String get cashoutTitle;

  /// No description provided for @cashoutEntry.
  ///
  /// In fa, this message translates to:
  /// **'برداشت نقدی به حساب بانکی'**
  String get cashoutEntry;

  /// No description provided for @cashoutAvailable.
  ///
  /// In fa, this message translates to:
  /// **'قابل برداشت'**
  String get cashoutAvailable;

  /// No description provided for @cashoutLimits.
  ///
  /// In fa, this message translates to:
  /// **'هر درخواست بین {min} تا {max} امتیاز'**
  String cashoutLimits(String min, String max);

  /// No description provided for @cashoutWindow.
  ///
  /// In fa, this message translates to:
  /// **'سقف ۳۰ روز: {max} امتیاز · باقی‌مانده {left}'**
  String cashoutWindow(String max, String left);

  /// No description provided for @cashoutStepPhone.
  ///
  /// In fa, this message translates to:
  /// **'تأیید پیامکی'**
  String get cashoutStepPhone;

  /// No description provided for @cashoutStepPhoneBody.
  ///
  /// In fa, this message translates to:
  /// **'هر مرحله با کدی که به {phone} پیامک می‌شود تأیید می‌شود.'**
  String cashoutStepPhoneBody(String phone);

  /// No description provided for @cashoutStepIdentity.
  ///
  /// In fa, this message translates to:
  /// **'مشخصات هویتی'**
  String get cashoutStepIdentity;

  /// No description provided for @cashoutStepIdentityEmpty.
  ///
  /// In fa, this message translates to:
  /// **'نام، نام خانوادگی، کد ملی و تاریخ تولد مطابق کارت ملی'**
  String get cashoutStepIdentityEmpty;

  /// No description provided for @cashoutStepBank.
  ///
  /// In fa, this message translates to:
  /// **'حساب بانکی'**
  String get cashoutStepBank;

  /// No description provided for @cashoutStepBankEmpty.
  ///
  /// In fa, this message translates to:
  /// **'شماره شبای حسابی که به نام خودت است'**
  String get cashoutStepBankEmpty;

  /// No description provided for @cashoutStepRequest.
  ///
  /// In fa, this message translates to:
  /// **'درخواست واریز'**
  String get cashoutStepRequest;

  /// No description provided for @cashoutStatusPending.
  ///
  /// In fa, this message translates to:
  /// **'در انتظار بررسی'**
  String get cashoutStatusPending;

  /// No description provided for @cashoutStatusVerified.
  ///
  /// In fa, this message translates to:
  /// **'تأیید شده'**
  String get cashoutStatusVerified;

  /// No description provided for @cashoutStatusRejected.
  ///
  /// In fa, this message translates to:
  /// **'رد شده'**
  String get cashoutStatusRejected;

  /// No description provided for @cashoutIdentitySubmit.
  ///
  /// In fa, this message translates to:
  /// **'ثبت مشخصات'**
  String get cashoutIdentitySubmit;

  /// No description provided for @cashoutIdentityFix.
  ///
  /// In fa, this message translates to:
  /// **'اصلاح مشخصات'**
  String get cashoutIdentityFix;

  /// No description provided for @cashoutFirstName.
  ///
  /// In fa, this message translates to:
  /// **'نام'**
  String get cashoutFirstName;

  /// No description provided for @cashoutLastName.
  ///
  /// In fa, this message translates to:
  /// **'نام خانوادگی'**
  String get cashoutLastName;

  /// No description provided for @cashoutNationalCode.
  ///
  /// In fa, this message translates to:
  /// **'کد ملی'**
  String get cashoutNationalCode;

  /// No description provided for @cashoutBirthDate.
  ///
  /// In fa, this message translates to:
  /// **'تاریخ تولد'**
  String get cashoutBirthDate;

  /// No description provided for @cashoutDay.
  ///
  /// In fa, this message translates to:
  /// **'روز'**
  String get cashoutDay;

  /// No description provided for @cashoutMonth.
  ///
  /// In fa, this message translates to:
  /// **'ماه'**
  String get cashoutMonth;

  /// No description provided for @cashoutYear.
  ///
  /// In fa, this message translates to:
  /// **'سال'**
  String get cashoutYear;

  /// No description provided for @cashoutNameHint.
  ///
  /// In fa, this message translates to:
  /// **'با حروف فارسی و دقیقاً مطابق کارت ملی'**
  String get cashoutNameHint;

  /// No description provided for @cashoutNationalCodeInvalid.
  ///
  /// In fa, this message translates to:
  /// **'کد ملی معتبر نیست.'**
  String get cashoutNationalCodeInvalid;

  /// No description provided for @cashoutRequired.
  ///
  /// In fa, this message translates to:
  /// **'این قسمت را کامل کن.'**
  String get cashoutRequired;

  /// No description provided for @cashoutIdentityNote.
  ///
  /// In fa, this message translates to:
  /// **'پس از تأیید، مشخصات قابل تغییر نیست و حساب بانکی باید به همین نام باشد.'**
  String get cashoutIdentityNote;

  /// No description provided for @cashoutAddBank.
  ///
  /// In fa, this message translates to:
  /// **'افزودن حساب'**
  String get cashoutAddBank;

  /// No description provided for @cashoutSheba.
  ///
  /// In fa, this message translates to:
  /// **'شماره شبا'**
  String get cashoutSheba;

  /// No description provided for @cashoutShebaHint.
  ///
  /// In fa, this message translates to:
  /// **'۲۴ رقم بعد از IR'**
  String get cashoutShebaHint;

  /// No description provided for @cashoutShebaInvalid.
  ///
  /// In fa, this message translates to:
  /// **'شماره شبا معتبر نیست.'**
  String get cashoutShebaInvalid;

  /// No description provided for @cashoutShebaNote.
  ///
  /// In fa, this message translates to:
  /// **'حساب باید به نام «{name}» باشد؛ در غیر این صورت رد می‌شود.'**
  String cashoutShebaNote(String name);

  /// No description provided for @cashoutRemoveBank.
  ///
  /// In fa, this message translates to:
  /// **'حذف حساب'**
  String get cashoutRemoveBank;

  /// No description provided for @cashoutRemoveBankConfirm.
  ///
  /// In fa, this message translates to:
  /// **'این حساب حذف شود؟'**
  String get cashoutRemoveBankConfirm;

  /// No description provided for @cashoutAmount.
  ///
  /// In fa, this message translates to:
  /// **'مقدار برداشت (امتیاز)'**
  String get cashoutAmount;

  /// No description provided for @cashoutAmountRial.
  ///
  /// In fa, this message translates to:
  /// **'مبلغ واریزی: {value}'**
  String cashoutAmountRial(String value);

  /// No description provided for @cashoutAmountRange.
  ///
  /// In fa, this message translates to:
  /// **'مقدار باید بین {min} و {max} امتیاز باشد.'**
  String cashoutAmountRange(String min, String max);

  /// No description provided for @cashoutAll.
  ///
  /// In fa, this message translates to:
  /// **'حداکثر'**
  String get cashoutAll;

  /// No description provided for @cashoutDestination.
  ///
  /// In fa, this message translates to:
  /// **'واریز به'**
  String get cashoutDestination;

  /// No description provided for @cashoutSubmit.
  ///
  /// In fa, this message translates to:
  /// **'ثبت درخواست واریز'**
  String get cashoutSubmit;

  /// No description provided for @cashoutSubmitNote.
  ///
  /// In fa, this message translates to:
  /// **'امتیازها همین حالا از کیف پولت کم می‌شوند و در صورت رد یا لغو برمی‌گردند. واریز پس از بررسی و تأیید مالی انجام می‌شود.'**
  String get cashoutSubmitNote;

  /// No description provided for @cashoutSubmitted.
  ///
  /// In fa, this message translates to:
  /// **'درخواست ثبت شد؛ نتیجه را اطلاع می‌دهیم.'**
  String get cashoutSubmitted;

  /// No description provided for @cashoutNotYet.
  ///
  /// In fa, this message translates to:
  /// **'هنوز امکان درخواست نیست'**
  String get cashoutNotYet;

  /// No description provided for @cashoutHistory.
  ///
  /// In fa, this message translates to:
  /// **'درخواست‌های من'**
  String get cashoutHistory;

  /// No description provided for @cashoutHistoryEmpty.
  ///
  /// In fa, this message translates to:
  /// **'هنوز درخواستی ثبت نکرده‌ای.'**
  String get cashoutHistoryEmpty;

  /// No description provided for @cashoutCancel.
  ///
  /// In fa, this message translates to:
  /// **'لغو درخواست'**
  String get cashoutCancel;

  /// No description provided for @cashoutCancelConfirm.
  ///
  /// In fa, this message translates to:
  /// **'درخواست لغو شود؟ امتیازها به کیف پولت برمی‌گردند.'**
  String get cashoutCancelConfirm;

  /// No description provided for @cashoutCancelled.
  ///
  /// In fa, this message translates to:
  /// **'لغو شد و امتیازها برگشت.'**
  String get cashoutCancelled;

  /// No description provided for @cashoutReference.
  ///
  /// In fa, this message translates to:
  /// **'کد پیگیری: {ref}'**
  String cashoutReference(String ref);

  /// No description provided for @cashoutOtpTitle.
  ///
  /// In fa, this message translates to:
  /// **'کد تأیید'**
  String get cashoutOtpTitle;

  /// No description provided for @cashoutOtpBody.
  ///
  /// In fa, this message translates to:
  /// **'کد ارسال‌شده به {phone} را وارد کن.'**
  String cashoutOtpBody(String phone);

  /// No description provided for @cashoutOtpConfirm.
  ///
  /// In fa, this message translates to:
  /// **'تأیید'**
  String get cashoutOtpConfirm;

  /// No description provided for @cashoutOtpResendIn.
  ///
  /// In fa, this message translates to:
  /// **'ارسال دوباره تا {seconds} ثانیه'**
  String cashoutOtpResendIn(String seconds);

  /// No description provided for @cashoutOtpResend.
  ///
  /// In fa, this message translates to:
  /// **'ارسال دوباره کد'**
  String get cashoutOtpResend;

  /// No description provided for @cashoutDisabled.
  ///
  /// In fa, this message translates to:
  /// **'برداشت نقدی فعلاً فعال نیست.'**
  String get cashoutDisabled;

  /// No description provided for @walletFilterCashout.
  ///
  /// In fa, this message translates to:
  /// **'برداشت'**
  String get walletFilterCashout;

  /// No description provided for @cashoutTotalBalance.
  ///
  /// In fa, this message translates to:
  /// **'موجودی کل کیف پول: {value} امتیاز'**
  String cashoutTotalBalance(String value);

  /// No description provided for @cashoutImmature.
  ///
  /// In fa, this message translates to:
  /// **'{points} امتیاز پیاده‌روی اخیر، {days} روز پس از قطعی شدن قابل برداشت می‌شود.'**
  String cashoutImmature(String points, String days);

  /// No description provided for @cashoutStoreOnly.
  ///
  /// In fa, this message translates to:
  /// **'امتیاز دعوت، تبلیغ و امتیازهای هدیه فقط در فروشگاه قابل استفاده‌اند.'**
  String get cashoutStoreOnly;

  /// No description provided for @cashoutQueue.
  ///
  /// In fa, this message translates to:
  /// **'نوبت بررسی: {n}'**
  String cashoutQueue(String n);

  /// No description provided for @orgTitle.
  ///
  /// In fa, this message translates to:
  /// **'سازمان من'**
  String get orgTitle;

  /// No description provided for @orgJoinTitle.
  ///
  /// In fa, this message translates to:
  /// **'برنامه سلامت محل کارت'**
  String get orgJoinTitle;

  /// No description provided for @orgJoinBody.
  ///
  /// In fa, this message translates to:
  /// **'اگر شرکتت در گام‌یار عضو است، با کد سازمان به رتبه‌بندی همکاران و چالش‌های سازمانی بپیوند.'**
  String get orgJoinBody;

  /// No description provided for @orgCode.
  ///
  /// In fa, this message translates to:
  /// **'کد سازمان'**
  String get orgCode;

  /// No description provided for @orgPrivacy.
  ///
  /// In fa, this message translates to:
  /// **'همکارانت نام و قدم‌های هفتگی‌ات را در رتبه‌بندی می‌بینند؛ سازمان فقط آمار کلی می‌بیند، نه اطلاعات سلامت تو.'**
  String get orgPrivacy;

  /// No description provided for @orgJoin.
  ///
  /// In fa, this message translates to:
  /// **'عضویت'**
  String get orgJoin;

  /// No description provided for @orgLeave.
  ///
  /// In fa, this message translates to:
  /// **'خروج از سازمان'**
  String get orgLeave;

  /// No description provided for @orgLeaveConfirm.
  ///
  /// In fa, this message translates to:
  /// **'از «{name}» خارج می‌شوی؟'**
  String orgLeaveConfirm(String name);

  /// No description provided for @orgMembers.
  ///
  /// In fa, this message translates to:
  /// **'{n} عضو'**
  String orgMembers(String n);

  /// No description provided for @orgInactive.
  ///
  /// In fa, this message translates to:
  /// **'اشتراک سازمان فعال نیست.'**
  String get orgInactive;

  /// No description provided for @orgMyRank.
  ///
  /// In fa, this message translates to:
  /// **'رتبه من این هفته'**
  String get orgMyRank;

  /// No description provided for @orgMySteps.
  ///
  /// In fa, this message translates to:
  /// **'قدم‌های این هفته'**
  String get orgMySteps;

  /// No description provided for @orgDepartment.
  ///
  /// In fa, this message translates to:
  /// **'واحد'**
  String get orgDepartment;

  /// No description provided for @orgPickDepartment.
  ///
  /// In fa, this message translates to:
  /// **'انتخاب کن'**
  String get orgPickDepartment;

  /// No description provided for @orgChallenges.
  ///
  /// In fa, this message translates to:
  /// **'چالش‌های سازمان'**
  String get orgChallenges;

  /// No description provided for @orgEndsIn.
  ///
  /// In fa, this message translates to:
  /// **'پایان {when}'**
  String orgEndsIn(String when);

  /// No description provided for @orgWeekRanking.
  ///
  /// In fa, this message translates to:
  /// **'رتبه‌بندی همکاران (این هفته)'**
  String get orgWeekRanking;

  /// No description provided for @orgYou.
  ///
  /// In fa, this message translates to:
  /// **'تو'**
  String get orgYou;

  /// No description provided for @orgDepartments.
  ///
  /// In fa, this message translates to:
  /// **'میانگین قدم واحدها'**
  String get orgDepartments;

  /// No description provided for @orgSmallGroup.
  ///
  /// In fa, this message translates to:
  /// **'کمتر از ۳ نفر'**
  String get orgSmallGroup;

  /// No description provided for @orgAvg.
  ///
  /// In fa, this message translates to:
  /// **'{n} قدم'**
  String orgAvg(String n);

  /// No description provided for @profileOrganization.
  ///
  /// In fa, this message translates to:
  /// **'سازمان من'**
  String get profileOrganization;

  /// No description provided for @referralShareTextLink.
  ///
  /// In fa, this message translates to:
  /// **'با گام‌یار راه برو و جایزه بگیر! از این لینک نصب کن: {url}\nکد دعوت من: {code}'**
  String referralShareTextLink(String code, String url);

  /// No description provided for @authReferralPrefilled.
  ///
  /// In fa, this message translates to:
  /// **'کد دعوت از لینک دریافتی وارد شد.'**
  String get authReferralPrefilled;

  /// No description provided for @lockScreenSteps.
  ///
  /// In fa, this message translates to:
  /// **'قدم‌های امروز روی صفحه قفل'**
  String get lockScreenSteps;

  /// No description provided for @lockScreenStepsHint.
  ///
  /// In fa, this message translates to:
  /// **'یک اعلان آرام و ثابت با قدم‌ها و پیشرفت هدف؛ برای ابزارک صفحه اصلی، صفحه اصلی گوشی را نگه دار و «ابزارک‌ها» را بزن.'**
  String get lockScreenStepsHint;

  /// No description provided for @weatherTitle.
  ///
  /// In fa, this message translates to:
  /// **'آب‌وهوا'**
  String get weatherTitle;

  /// No description provided for @weatherFeelsLike.
  ///
  /// In fa, this message translates to:
  /// **'احساس {temp}'**
  String weatherFeelsLike(String temp);

  /// No description provided for @weatherHumidity.
  ///
  /// In fa, this message translates to:
  /// **'رطوبت'**
  String get weatherHumidity;

  /// No description provided for @weatherWind.
  ///
  /// In fa, this message translates to:
  /// **'باد'**
  String get weatherWind;

  /// No description provided for @weatherGusts.
  ///
  /// In fa, this message translates to:
  /// **'تندباد'**
  String get weatherGusts;

  /// No description provided for @weatherUv.
  ///
  /// In fa, this message translates to:
  /// **'فرابنفش'**
  String get weatherUv;

  /// No description provided for @weatherAir.
  ///
  /// In fa, this message translates to:
  /// **'کیفیت هوا'**
  String get weatherAir;

  /// No description provided for @weatherEnableLocation.
  ///
  /// In fa, this message translates to:
  /// **'برای دیدن آب‌وهوای همین‌جا، اجازه موقعیت مکانی را بدهید.'**
  String get weatherEnableLocation;

  /// No description provided for @weatherEnableAction.
  ///
  /// In fa, this message translates to:
  /// **'نمایش'**
  String get weatherEnableAction;

  /// No description provided for @weatherLocationOff.
  ///
  /// In fa, this message translates to:
  /// **'مکان‌یاب گوشی خاموش است.'**
  String get weatherLocationOff;

  /// No description provided for @weatherUpdated.
  ///
  /// In fa, this message translates to:
  /// **'به‌روزرسانی {time}'**
  String weatherUpdated(String time);

  /// No description provided for @weatherUpdatedLabel.
  ///
  /// In fa, this message translates to:
  /// **'آخرین به‌روزرسانی'**
  String get weatherUpdatedLabel;

  /// No description provided for @weatherStale.
  ///
  /// In fa, this message translates to:
  /// **'(اتصال به سرویس هواشناسی برقرار نشد)'**
  String get weatherStale;

  /// No description provided for @weatherHourly.
  ///
  /// In fa, this message translates to:
  /// **'ساعت‌های پیش رو'**
  String get weatherHourly;

  /// No description provided for @weatherDaily.
  ///
  /// In fa, this message translates to:
  /// **'سه روز آینده'**
  String get weatherDaily;

  /// No description provided for @weatherAdvice.
  ///
  /// In fa, this message translates to:
  /// **'توصیه‌ها برای پیاده‌روی'**
  String get weatherAdvice;

  /// No description provided for @weatherDetails.
  ///
  /// In fa, this message translates to:
  /// **'جزئیات'**
  String get weatherDetails;

  /// No description provided for @weatherSunrise.
  ///
  /// In fa, this message translates to:
  /// **'طلوع'**
  String get weatherSunrise;

  /// No description provided for @weatherSunset.
  ///
  /// In fa, this message translates to:
  /// **'غروب'**
  String get weatherSunset;

  /// No description provided for @weatherElevation.
  ///
  /// In fa, this message translates to:
  /// **'ارتفاع از دریا'**
  String get weatherElevation;

  /// No description provided for @weatherMeters.
  ///
  /// In fa, this message translates to:
  /// **'{value} متر'**
  String weatherMeters(String value);

  /// No description provided for @weatherAround.
  ///
  /// In fa, this message translates to:
  /// **'اطراف شما'**
  String get weatherAround;

  /// No description provided for @weatherAroundEmpty.
  ///
  /// In fa, this message translates to:
  /// **'در دو کیلومتری شما مکان پاداش‌دار فعالی نیست.'**
  String get weatherAroundEmpty;

  /// No description provided for @weatherToday.
  ///
  /// In fa, this message translates to:
  /// **'امروز'**
  String get weatherToday;

  /// No description provided for @weatherNow.
  ///
  /// In fa, this message translates to:
  /// **'اکنون'**
  String get weatherNow;

  /// No description provided for @weatherSource.
  ///
  /// In fa, this message translates to:
  /// **'داده‌های هواشناسی: Open-Meteo'**
  String get weatherSource;

  /// No description provided for @weatherUvLow.
  ///
  /// In fa, this message translates to:
  /// **'کم'**
  String get weatherUvLow;

  /// No description provided for @weatherUvModerate.
  ///
  /// In fa, this message translates to:
  /// **'متوسط'**
  String get weatherUvModerate;

  /// No description provided for @weatherUvHigh.
  ///
  /// In fa, this message translates to:
  /// **'زیاد'**
  String get weatherUvHigh;

  /// No description provided for @weatherUvVeryHigh.
  ///
  /// In fa, this message translates to:
  /// **'خیلی زیاد'**
  String get weatherUvVeryHigh;

  /// No description provided for @weatherUvExtreme.
  ///
  /// In fa, this message translates to:
  /// **'شدید'**
  String get weatherUvExtreme;

  /// No description provided for @walkModeWalking.
  ///
  /// In fa, this message translates to:
  /// **'پیاده‌روی'**
  String get walkModeWalking;

  /// No description provided for @walkModeRunning.
  ///
  /// In fa, this message translates to:
  /// **'دویدن'**
  String get walkModeRunning;

  /// No description provided for @walkModeCycling.
  ///
  /// In fa, this message translates to:
  /// **'دوچرخه‌سواری'**
  String get walkModeCycling;

  /// No description provided for @walkModeVehicle.
  ///
  /// In fa, this message translates to:
  /// **'در خودرو'**
  String get walkModeVehicle;

  /// No description provided for @walkModeStill.
  ///
  /// In fa, this message translates to:
  /// **'ایستاده'**
  String get walkModeStill;

  /// No description provided for @walkCyclingHint.
  ///
  /// In fa, this message translates to:
  /// **'دوچرخه‌سواری با مسافت GPS امتیاز می‌گیرد: {points} امتیاز در هر کیلومتر (کمتر از پیاده‌روی).'**
  String walkCyclingHint(String points);

  /// No description provided for @walkCyclingNeedsGps.
  ///
  /// In fa, this message translates to:
  /// **'برای امتیاز دوچرخه‌سواری، دفعه بعد «ثبت مسیر با GPS» را روشن کنید.'**
  String get walkCyclingNeedsGps;

  /// No description provided for @walkVehicleHint.
  ///
  /// In fa, this message translates to:
  /// **'در خودرو قدم‌ها و مسافت امتیاز ندارند.'**
  String get walkVehicleHint;

  /// No description provided for @walkCyclingDistance.
  ///
  /// In fa, this message translates to:
  /// **'مسافت دوچرخه'**
  String get walkCyclingDistance;

  /// No description provided for @walkSpeed.
  ///
  /// In fa, this message translates to:
  /// **'سرعت'**
  String get walkSpeed;

  /// No description provided for @activityCycling.
  ///
  /// In fa, this message translates to:
  /// **'دوچرخه‌سواری'**
  String get activityCycling;

  /// No description provided for @activityCyclingKm.
  ///
  /// In fa, this message translates to:
  /// **'{km} کیلومتر دوچرخه'**
  String activityCyclingKm(String km);

  /// No description provided for @homeCycling.
  ///
  /// In fa, this message translates to:
  /// **'دوچرخه'**
  String get homeCycling;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['fa'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'fa':
      return AppLocalizationsFa();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
