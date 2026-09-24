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

  /// No description provided for @comingNextTitle.
  ///
  /// In fa, this message translates to:
  /// **'این بخش در حال آماده‌سازی است'**
  String get comingNextTitle;

  /// No description provided for @comingNextBody.
  ///
  /// In fa, this message translates to:
  /// **'به‌زودی از همین‌جا در دسترس خواهد بود.'**
  String get comingNextBody;

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
