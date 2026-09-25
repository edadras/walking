<?php

namespace Database\Seeders;

use App\Domain\Fraud\RuleRegistry;
use App\Domain\Gamification\XpService;
use App\Domain\Reward\RewardRules;
use App\Domain\Wallet\ConversionRate;
use App\Enums\AdFormat;
use App\Enums\AdminRole;
use App\Enums\RewardRuleType;
use App\Models\Achievement;
use App\Models\Admin;
use App\Models\AdPlacement;
use App\Models\AdProvider;
use App\Models\CmsPage;
use App\Models\Faq;
use App\Models\FeatureFlag;
use App\Models\Level;
use App\Models\PointConversionRate;
use App\Models\Quest;
use App\Models\RewardRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Idempotent reference data required in every environment (including production). */
class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('walk.feature_flags') as $key => $definition) {
            FeatureFlag::query()->firstOrCreate(['key' => $key], [
                'is_enabled' => $definition['enabled'],
                'description' => $definition['description'],
            ]);
        }

        app(RuleRegistry::class)->sync();

        if (PointConversionRate::query()->doesntExist()) {
            app(ConversionRate::class)->set((int) config('walk.default_rial_per_point'));
        }

        if (RewardRule::query()->doesntExist()) {
            foreach ($this->rewardRules() as $rule) {
                RewardRule::query()->create($rule);
            }
        }
        app(RewardRules::class)->flush();

        if (Level::query()->doesntExist()) {
            Level::query()->insert(XpService::curve());
        }

        $this->seedAdInventory();

        foreach ($this->achievements() as $i => [$key, $name, $description, $icon, $metric, $threshold, $xp, $points]) {
            Achievement::query()->firstOrCreate(['key' => $key], compact('name', 'description', 'icon', 'metric', 'threshold') + ['xp_reward' => $xp, 'point_reward' => $points, 'sort' => $i]);
        }

        // Starter missions; admins tune or add more in the panel.
        foreach ($this->quests() as $i => [$key, $title, $description, $period, $metric, $target, $points, $xp]) {
            Quest::query()->firstOrCreate(['key' => $key], compact('title', 'description', 'period', 'metric', 'target') + ['reward_points' => $points, 'reward_xp' => $xp, 'sort' => $i]);
        }

        foreach ($this->pages() as $slug => [$title, $body]) {
            CmsPage::query()->firstOrCreate(['slug' => $slug], ['title' => $title, 'body' => $body]);
        }

        if (Faq::query()->doesntExist()) {
            foreach ($this->faqs() as $i => [$category, $q, $a]) {
                Faq::query()->create(['category' => $category, 'question' => $q, 'answer' => $a, 'sort' => $i]);
            }
        }

        if ($email = env('ADMIN_EMAIL')) {
            Admin::query()->firstOrCreate(['email' => $email], [
                'name' => 'مدیر ارشد',
                'password' => env('ADMIN_PASSWORD') ?: Str::password(20),
                'role' => AdminRole::SuperAdmin,
            ]);
        }
    }

    /** @return list<array{0:string,1:string,2:string,3:string,4:string,5:int,6:int,7:int}> */
    /** Internal network on; external adapters stay off until their official S2S docs are verified. */
    private function seedAdInventory(): void
    {
        $internal = AdProvider::query()->firstOrCreate(['key' => AdProvider::INTERNAL], ['name' => 'تبلیغات داخلی', 'is_enabled' => true]);
        foreach ([['yektanet', 'یکتانت'], ['adsell', 'ادسل']] as [$key, $name]) {
            AdProvider::query()->firstOrCreate(['key' => $key], [
                'name' => $name, 'is_enabled' => false,
                'notes' => 'غیرفعال: پیش از فعال‌سازی باید مستندات رسمی S2S بررسی و با قرارداد /webhooks/ads/'.$key.' تطبیق داده شود.',
            ]);
        }
        foreach ([
            ['home_banner', 'خانه — پایین صفحه', AdFormat::Banner],
            ['activity_banner', 'فعالیت — بالای تاریخچه', AdFormat::Banner],
            ['rewards_native', 'مرکز جایزه — کارت', AdFormat::Native],
            ['rewarded_default', 'تبلیغ جایزه‌دار', AdFormat::Rewarded],
        ] as [$key, $name, $format]) {
            AdPlacement::query()->firstOrCreate(['key' => $key], ['name' => $name, 'format' => $format, 'ad_provider_id' => $internal->id]);
        }
    }

    private function achievements(): array
    {
        return [
            ['first_5k_day', 'اولین ۵٬۰۰۰', 'در یک روز ۵٬۰۰۰ قدم تأییدشده بردار.', 'footsteps', 'daily_steps', 5000, 50, 0],
            ['first_10k_day', 'اولین ۱۰٬۰۰۰ قدم', 'در یک روز ۱۰٬۰۰۰ قدم تأییدشده بردار.', 'footsteps', 'daily_steps', 10000, 100, 20],
            ['day_20k', 'روز پرقدم', 'در یک روز ۲۰٬۰۰۰ قدم بردار.', 'bolt', 'daily_steps', 20000, 200, 50],
            ['streak_3', '۳ روز متوالی', 'سه روز پشت سر هم به هدفت برس.', 'chain', 'streak_days', 3, 60, 0],
            ['streak_7', '۷ روز متوالی', 'یک هفته کامل هر روز به هدفت برس.', 'chain', 'streak_days', 7, 150, 30],
            ['streak_30', '۳۰ روز متوالی', 'یک ماه بدون وقفه به هدفت برس.', 'chain', 'streak_days', 30, 600, 150],
            ['total_100k', '۱۰۰٬۰۰۰ قدم', 'مجموع قدم‌های تأییدشده‌ات به ۱۰۰ هزار برسد.', 'trail', 'total_steps', 100000, 200, 30],
            ['total_1m', 'یک میلیون قدم', 'مجموع قدم‌هایت به یک میلیون برسد.', 'trail', 'total_steps', 1000000, 1000, 200],
            ['distance_100km', '۱۰۰ کیلومتر', 'مجموع مسافت پیاده‌روی‌ات ۱۰۰ کیلومتر شود.', 'route', 'total_distance_m', 100000, 300, 50],
            ['active_30', '۳۰ روز فعال', 'در ۳۰ روز مختلف راه برو.', 'calendar', 'active_days', 30, 250, 0],
            ['challenge_first', 'اولین چالش', 'اولین چالشت را کامل کن.', 'flag', 'challenges_completed', 1, 100, 0],
        ];
    }

    /** Starting economy; every value is editable in the admin panel. */
    /** @return list<array{0:string,1:string,2:string,3:string,4:string,5:int,6:int,7:int}> */
    private function quests(): array
    {
        return [
            ['daily_steps_6k', 'قدم‌های امروز', 'امروز ۶ هزار قدم تأییدشده بردار', 'daily', 'steps', 6000, 10, 20],
            ['daily_active_20', 'بیست دقیقه تحرک', 'امروز ۲۰ دقیقه فعالیت داشته باش', 'daily', 'active_minutes', 20, 8, 15],
            ['daily_water_2l', 'آب کافی', 'امروز ۲ لیتر آب بنوش و ثبت کن', 'daily', 'water_ml', 2000, 5, 10],
            ['weekly_goal_5', 'پنج روز هدف', 'این هفته ۵ روز به هدف روزانه‌ات برس', 'weekly', 'goal_days', 5, 60, 100],
            ['weekly_walks_3', 'سه پیاده‌روی ثبت‌شده', 'این هفته ۳ پیاده‌روی را با دکمه «شروع پیاده‌روی» ثبت کن', 'weekly', 'active_walks', 3, 40, 60],
            ['weekly_distance_25k', 'بیست‌وپنج کیلومتر', 'این هفته ۲۵ کیلومتر راه برو', 'weekly', 'distance_m', 25000, 50, 80],
            ['weekly_sponsor_1', 'سر زدن به اسپانسر', 'این هفته از یکی از شعبه‌های اسپانسر بازدید کن', 'weekly', 'sponsor_visits', 1, 20, 40],
        ];
    }

    private function rewardRules(): array
    {
        return [
            ['name' => 'نرخ پایه: ۱۰۰۰ قدم = ۱۰ امتیاز', 'rule_type' => RewardRuleType::StepRate, 'steps' => 1000, 'points' => 10],
            ['name' => 'سقف قدم پاداش‌دار روزانه', 'rule_type' => RewardRuleType::MaxRewardedSteps, 'cap' => 20000],
            ['name' => 'سقف امتیاز روزانه', 'rule_type' => RewardRuleType::DailyCap, 'cap' => 300],
            ['name' => 'سقف امتیاز هفتگی', 'rule_type' => RewardRuleType::WeeklyCap, 'cap' => 1500],
            ['name' => 'پاداش هدف روزانه', 'rule_type' => RewardRuleType::GoalBonus, 'points' => 20],
            ['name' => 'جمعه‌ها ×۱٫۵', 'rule_type' => RewardRuleType::Multiplier, 'days_of_week' => '5', 'multiplier' => 1.5],
            ['name' => 'پاداش روزهای متوالی', 'rule_type' => RewardRuleType::StreakBonus, 'params' => ['7' => 30, '14' => 60, '30' => 150]],
        ];
    }

    /** @return array<string, array{0:string,1:string}> */
    private function pages(): array
    {
        return [
            'about' => ['درباره ما', "گام‌یار به شما کمک می‌کند هر روز کمی بیشتر حرکت کنید و برای این حرکت پاداش بگیرید.\n\nقدم‌های شما پس از بررسی به امتیاز تبدیل می‌شوند و می‌توانید با امتیاز از فروشگاه خرید کنید یا جایزه‌های اسپانسرها را دریافت کنید."],
            'terms' => ['قوانین و مقررات', "## استفاده از گام‌یار\n\n- هر کاربر فقط یک حساب مجاز دارد.\n- استفاده از هر روش شبیه‌سازی قدم، GPS جعلی یا دستکاری برنامه باعث حذف امتیازها و مسدود شدن حساب می‌شود.\n- امتیازها ارزش ریالی تقریبی دارند و فقط در گام‌یار قابل استفاده‌اند.\n- گام‌یار می‌تواند قوانین امتیازدهی را با اطلاع‌رسانی قبلی تغییر دهد."],
            'privacy' => ['حریم خصوصی', "## چه اطلاعاتی جمع‌آوری می‌کنیم؟\n\n- **شماره موبایل**: برای ورود. در هیچ بخش عمومی نمایش داده نمی‌شود.\n- **داده‌های حرکتی**: تعداد قدم و خلاصه دقیقه‌ای حرکت (نه داده خام سنسور) برای ثبت فعالیت و جلوگیری از تقلب.\n- **موقعیت مکانی**: فقط وقتی خودتان پیاده‌روی با مسیر را شروع کنید یا از «جایزه‌های اطراف من» استفاده کنید. موقعیت شما هرگز به‌صورت دائمی در پس‌زمینه ثبت نمی‌شود.\n- **اطلاعات دستگاه**: مدل و نسخه سیستم‌عامل برای امنیت حساب.\n\n## حذف حساب\n\nاز بخش پروفایل می‌توانید درخواست حذف حساب بدهید. اطلاعات شخصی شما حذف یا ناشناس می‌شود؛ سوابق مالی (تراکنش امتیاز و سفارش) به‌صورت ناشناس برای الزامات قانونی نگهداری می‌شوند."],
            'guide' => ['راهنما', 'برنامه را نصب کنید، اجازه ثبت فعالیت بدنی را بدهید و مثل همیشه راه بروید. گام‌یار قدم‌های شما را در پس‌زمینه با کمترین مصرف باتری ثبت می‌کند.'],
            'how-to-earn' => ['نحوه دریافت امتیاز', "- **قدم روزانه**: به ازای قدم‌های تأییدشده امتیاز می‌گیرید.\n- **هدف روزانه**: رسیدن به هدف، امتیاز جایزه دارد.\n- **روزهای متوالی**: هرچه زنجیره روزهایتان طولانی‌تر باشد، جایزه بیشتری می‌گیرید.\n- **چالش‌ها و مکان‌های اسپانسری**: با شرکت در چالش‌ها و مراجعه به فروشگاه‌های همکار امتیاز بیشتری بگیرید."],
            'reward-rules' => ['قوانین پاداش', "- فقط **قدم‌های تأییدشده** امتیاز دارند.\n- امتیازها ابتدا «در حال بررسی» هستند و پس از بررسی امنیتی قابل استفاده می‌شوند.\n- برای امتیاز روزانه سقف وجود دارد.\n- ارزش ریالی امتیاز ممکن است تغییر کند؛ تراکنش‌های گذشته تحت تأثیر قرار نمی‌گیرند."],
        ];
    }

    /** @return list<array{0:string,1:string,2:string}> */
    private function faqs(): array
    {
        return [
            ['steps', 'چرا همه قدم‌هایم امتیاز نگرفت؟', 'فقط قدم‌هایی که بررسی امنیتی را با موفقیت پشت سر بگذارند «تأییدشده» هستند. قدم‌هایی که مثلاً در خودرو یا با تکان دادن گوشی ثبت شده باشند حذف می‌شوند. همچنین امتیاز روزانه سقف دارد.'],
            ['steps', 'آیا برنامه باید همیشه باز باشد؟', 'خیر. گام‌یار از شمارنده قدم سخت‌افزاری گوشی استفاده می‌کند که در پس‌زمینه و با مصرف بسیار کم باتری کار می‌کند.'],
            ['points', 'امتیاز «در حال بررسی» یعنی چه؟', 'هر امتیاز پس از ثبت، مدتی کوتاه برای بررسی امنیتی نگه داشته می‌شود و سپس به موجودی قابل استفاده اضافه می‌شود.'],
            ['points', 'ارزش ریالی امتیاز چطور محاسبه می‌شود؟', 'ارزش ریالی بر اساس نرخ تبدیل فعلی نمایش داده می‌شود و تقریبی است.'],
            ['account', 'آیا شماره موبایلم به دیگران نمایش داده می‌شود؟', 'خیر. در رتبه‌بندی فقط نام نمایشی و تصویر شما (در صورت تمایل) نمایش داده می‌شود و می‌توانید آن را در تنظیمات حریم خصوصی خاموش کنید.'],
        ];
    }
}
