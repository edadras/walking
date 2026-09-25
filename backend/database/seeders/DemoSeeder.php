<?php

namespace Database\Seeders;

use App\Domain\Activity\ActivityEstimator;
use App\Domain\Activity\DailyActivityAggregator;
use App\Domain\Cashout\IranianId;
use App\Domain\Gamification\ProgressService;
use App\Domain\Reward\RewardEngine;
use App\Domain\Settings\FeatureFlags;
use App\Domain\Wallet\WalletService;
use App\Enums\AdFormat;
use App\Enums\AdminRole;
use App\Enums\CampaignStatus;
use App\Enums\ChallengeStatus;
use App\Enums\ChallengeType;
use App\Enums\CouponStatus;
use App\Enums\DiscountType;
use App\Enums\LocationStatus;
use App\Enums\ProductType;
use App\Enums\SessionKind;
use App\Enums\SessionRewardStatus;
use App\Enums\SessionStatus;
use App\Enums\SponsorRole;
use App\Enums\SponsorStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\VerificationMethod;
use App\Models\Ad;
use App\Models\AdCampaign;
use App\Models\Admin;
use App\Models\AdPlacement;
use App\Models\BankAccount;
use App\Models\Campaign;
use App\Models\CashoutRequest;
use App\Models\Category;
use App\Models\Challenge;
use App\Models\Coupon;
use App\Models\Device;
use App\Models\FeatureFlag;
use App\Models\Location;
use App\Models\PointTransaction;
use App\Models\Product;
use App\Models\ProductCode;
use App\Models\Sponsor;
use App\Models\SponsorUser;
use App\Models\User;
use App\Models\UserIdentity;
use App\Models\WalkingSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo data so the app and panels can be reviewed without empty screens.
 * Never runs in production (see DatabaseSeeder). Extended in later phases.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        Admin::query()->firstOrCreate(['email' => 'admin@gamyar.test'], [
            'name' => 'مدیر نمایشی',
            'password' => 'password',
            'role' => AdminRole::SuperAdmin,
        ]);

        foreach ([
            ['title' => 'هفته ۵۰ هزار قدمی', 'description' => 'در ۷ روز ۵۰٬۰۰۰ قدم تأییدشده بردار.', 'type' => ChallengeType::Weekly, 'metric' => 'steps', 'target_value' => 50000, 'reward_points' => 500, 'reward_xp' => 300, 'starts_at' => now()->subDays(2), 'ends_at' => now()->addDays(5)],
            ['title' => 'روز ۱۲ هزار قدمی', 'description' => 'در یک روز ۱۲٬۰۰۰ قدم بردار.', 'type' => ChallengeType::Daily, 'metric' => 'steps', 'target_value' => 12000, 'reward_points' => 100, 'reward_xp' => 100, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(6)],
            ['title' => 'ماراتن ماه مهر', 'description' => 'در این ماه ۴۲ کیلومتر پیاده‌روی کن.', 'type' => ChallengeType::Distance, 'metric' => 'distance', 'target_value' => 42000, 'reward_points' => 800, 'reward_xp' => 500, 'starts_at' => now()->subDays(1), 'ends_at' => now()->addDays(28)],
        ] as $challenge) {
            Challenge::query()->firstOrCreate(['title' => $challenge['title']], [...$challenge, 'status' => ChallengeStatus::Active, 'created_by_type' => 'admin']);
        }

        $this->seedSponsor();
        $this->seedAds();
        $this->seedStore();

        // Deterministic "randomness" so every seed produces the same demo world.
        mt_srand(1405);

        $names = ['علی', 'سارا', 'محمد', 'مریم', 'رضا', 'زهرا', 'حسین', 'نگار', 'امیر', 'فاطمه', 'مهدی', 'الهام'];
        foreach ($names as $i => $name) {
            $user = User::query()->where('phone', sprintf('+98912000%04d', $i + 1))->first() ?? User::factory()->create([
                'phone' => sprintf('+98912000%04d', $i + 1),
                'display_name' => $name,
                'created_at' => now()->subDays(60 - $i * 3),
                'last_active_at' => now()->subHours($i * 5),
            ]);
            $user->profile->update(['height_cm' => mt_rand(155, 190), 'weight_kg' => mt_rand(52, 95)]);

            if ($user->walkingSessions()->doesntExist()) {
                $this->seedWalkingHistory($user, fitness: 0.6 + ($i % 5) * 0.2);
            }
        }

        $this->seedCashout();
    }

    /**
     * A payout queue in every state, reviewed by two finance admins
     * (finance@ approves, finance2@ records transfers; password: password).
     */
    private function seedCashout(): void
    {
        if (UserIdentity::query()->exists()) {
            return;
        }
        $wallet = app(WalletService::class);
        $approver = Admin::query()->firstOrCreate(['email' => 'finance@gamyar.test'], ['name' => 'کارشناس مالی', 'password' => 'password', 'role' => AdminRole::Finance]);
        $payer = Admin::query()->firstOrCreate(['email' => 'finance2@gamyar.test'], ['name' => 'مسئول واریز', 'password' => 'password', 'role' => AdminRole::Finance]);
        $nationalCode = function (string $first9): string {
            $sum = 0;
            for ($i = 0; $i < 9; $i++) {
                $sum += (int) $first9[$i] * (10 - $i);
            }

            return $first9.(($r = $sum % 11) < 2 ? $r : 11 - $r);
        };
        $sheba = function (string $bank, string $account): string {
            $mod = 0;
            foreach (str_split($bank.$account.'182700', 7) as $chunk) {
                $mod = (int) (($mod.$chunk) % 97);
            }

            return 'IR'.str_pad((string) (98 - $mod), 2, '0', STR_PAD_LEFT).$bank.$account;
        };

        // [phone suffix, last name, identity status, bank code, account status, requests: [points, status, days ago]]
        $people = [
            [1, 'رضایی', 'verified', '012', 'verified', [[8000, CashoutRequest::PAID, 12], [10000, CashoutRequest::APPROVED, 1]]],
            [2, 'کریمی', 'verified', '056', 'verified', [[6000, CashoutRequest::PENDING, 0]]],
            [4, 'احمدی', 'verified', '017', 'verified', [[5000, CashoutRequest::REJECTED, 20], [12000, CashoutRequest::PENDING, 0]]],
            [6, 'موسوی', 'verified', '054', 'pending', []],
            [8, 'حسینی', 'pending', '019', 'pending', []],
            [10, 'نوری', 'pending', '057', 'pending', []],
        ];
        foreach ($people as $n => [$suffix, $last, $idStatus, $bank, $accStatus, $requests]) {
            $user = User::query()->where('phone', sprintf('+98912000%04d', $suffix))->first();
            if ($user === null) {
                continue;
            }
            $code = $nationalCode(sprintf('00%07d', 1234567 + $n * 7919));
            $reviewed = $idStatus === 'verified' ? ['reviewed_by' => $approver->id, 'reviewed_at' => now()->subDays(25)] : [];
            $identity = UserIdentity::query()->create(['user_id' => $user->id, 'first_name' => strtok((string) $user->display_name, ' '), 'last_name' => $last,
                'national_code' => $code, 'national_code_hash' => UserIdentity::hashNationalCode($code), 'birth_date' => now()->subYears(24 + $n * 3)->subDays($n * 40)->toDateString(),
                'status' => $idStatus, 'submitted_at' => now()->subDays(26 - $n), ...$reviewed]);
            $iban = $sheba($bank, sprintf('%019d', 4821000000 + $n * 104729));
            $account = BankAccount::query()->create(['user_id' => $user->id, 'iban' => $iban, 'iban_hash' => BankAccount::hashIban($iban), 'iban_last4' => substr($iban, -4),
                'bank_name' => IranianId::bankName($iban), 'holder_name' => $identity->fullName(), 'status' => $accStatus]
                + ($accStatus === 'verified' ? ['reviewed_by' => $approver->id, 'reviewed_at' => now()->subDays(24)] : []));

            foreach ($requests as $k => [$points, $status, $ago]) {
                $wallet->credit($user, $points, TransactionType::Adjustment, "demo:cashout-topup:{$user->id}:$k", 'شارژ نمایشی');
                $r = CashoutRequest::query()->create(['user_id' => $user->id, 'bank_account_id' => $account->id, 'points' => $points, 'rial_per_point' => 500,
                    'amount_rial' => $points * 500, 'status' => CashoutRequest::PENDING, 'idempotency_key' => "demo-$user->id-$k"]);
                $r->forceFill(['created_at' => now()->subDays($ago)->subHours(3)])->save();
                $tx = $wallet->debit($user, $points, TransactionType::Cashout, 'cashout:'.$r->public_id, 'برداشت نقدی به حساب '.$account->bank_name, $r);
                $r->forceFill(['debit_transaction_id' => $tx->id])->save();
                if ($status === CashoutRequest::REJECTED) {
                    $refund = $wallet->credit($user, $points, TransactionType::Refund, 'refund:cashout:'.$r->public_id, 'بازگشت امتیاز برداشت', $r);
                    $r->forceFill(['status' => $status, 'rejected_by' => $approver->id, 'rejection_reason' => 'شماره شبای قبلی به نام شخص دیگری بود.', 'refund_transaction_id' => $refund->id])->save();
                }
                if (in_array($status, [CashoutRequest::APPROVED, CashoutRequest::PAID], true)) {
                    $r->forceFill(['status' => CashoutRequest::APPROVED, 'approved_by' => $approver->id, 'approved_at' => now()->subDays($ago)->subHours(1)])->save();
                }
                if ($status === CashoutRequest::PAID) {
                    $r->forceFill(['status' => $status, 'paid_by' => $payer->id, 'paid_at' => now()->subDays($ago - 1), 'bank_reference' => 'PAYA-'.(58213 + $k)])->save();
                }
            }
        }
    }

    /** An approved café with two Tehran branches, a QR campaign and a coupon (login: sponsor@gamyar.test / password). */
    private function seedSponsor(): void
    {
        if (Sponsor::query()->where('name', 'کافه قدم')->exists()) {
            return;
        }
        $sponsor = Sponsor::query()->create(['name' => 'کافه قدم', 'description' => 'قهوه تازه برای قدم‌زن‌ها', 'status' => SponsorStatus::Approved, 'approved_at' => now()]);
        $sponsor->forceFill(['point_budget' => 50_000])->save();
        SponsorUser::query()->create(['sponsor_id' => $sponsor->id, 'name' => 'مالک کافه', 'email' => 'sponsor@gamyar.test', 'password' => 'password', 'role' => SponsorRole::Owner]);
        SponsorUser::query()->create(['sponsor_id' => $sponsor->id, 'name' => 'صندوق‌دار', 'email' => 'cashier@gamyar.test', 'password' => 'password', 'role' => SponsorRole::Cashier]);

        $branches = collect([
            ['name' => 'شعبه پارک ملت', 'address' => 'خیابان ولیعصر، ضلع غربی پارک ملت', 'latitude' => 35.7790, 'longitude' => 51.4145],
            ['name' => 'شعبه پل طبیعت', 'address' => 'بزرگراه مدرس، پل طبیعت', 'latitude' => 35.7545, 'longitude' => 51.4197],
        ])->map(fn ($b) => Location::query()->create([...$b, 'sponsor_id' => $sponsor->id, 'city' => 'تهران', 'radius_m' => 60, 'status' => LocationStatus::Approved,
            'opening_hours' => array_fill_keys(Location::DAYS, [['08:00', '23:00']])]));

        $coupon = Coupon::query()->create(['sponsor_id' => $sponsor->id, 'title' => '۲۰٪ تخفیف نوشیدنی گرم', 'discount_type' => DiscountType::Percent, 'discount_value' => 20,
            'terms' => 'فقط در شعبه‌های کافه قدم، یک بار.', 'valid_days' => 14, 'status' => CouponStatus::Active]);
        Coupon::query()->create(['sponsor_id' => $sponsor->id, 'title' => 'یک کلوچه رایگان', 'discount_type' => DiscountType::FreeItem, 'discount_value' => 0,
            'valid_days' => 30, 'claimable' => true, 'point_cost' => 150, 'usage_limit' => 500, 'status' => CouponStatus::Active]);

        $campaign = Campaign::query()->create([
            'sponsor_id' => $sponsor->id, 'name' => 'قدم بزن، قهوه بگیر', 'description' => 'به یکی از شعبه‌ها سر بزن، ۳ دقیقه بمان و QR صندوق را اسکن کن.',
            'verification_method' => VerificationMethod::GeofenceQr, 'min_stay_seconds' => 180, 'reward_points' => 40, 'coupon_id' => $coupon->id,
            'max_rewards_per_user' => 4, 'cooldown_hours' => 24, 'point_budget' => 20_000, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
            'status' => CampaignStatus::Active, 'approved_at' => now(),
        ]);
        $campaign->locations()->attach($branches->pluck('id'));
    }

    /** In-house ads (and the flags that show them) so ad slots aren't empty in the demo. */
    private function seedAds(): void
    {
        foreach (['ads', 'rewarded_ads'] as $flag) {
            FeatureFlag::query()->where('key', $flag)->update(['is_enabled' => true, 'rollout_percent' => 100]);
        }
        if (AdCampaign::query()->exists()) {
            return;
        }
        $placements = AdPlacement::query()->pluck('id', 'key');
        foreach ([
            ['چالش هفته', ['home_banner', 'activity_banner'], 0, 0, ['format' => AdFormat::Banner, 'title' => 'چالش ۵۰ هزار قدمی این هفته', 'body' => 'همین حالا شرکت کن و ۵۰۰ امتیاز بگیر', 'cta_label' => 'شرکت', 'action_url' => '/challenges']],
            ['معرفی کافه قدم', ['rewards_native'], 0, 0, ['format' => AdFormat::Native, 'title' => 'کافه قدم', 'body' => 'به شعبه‌های کافه قدم سر بزن و قهوه هدیه بگیر', 'cta_label' => 'جایزه‌های اطراف', 'action_url' => '/nearby']],
            ['تماشا و امتیاز', ['rewarded_default'], 10, 20_000, ['format' => AdFormat::Rewarded, 'title' => 'کفش مخصوص پیاده‌روی', 'body' => 'سبک، نرم و مناسب قدم‌های روزانه', 'cta_label' => 'مشاهده', 'action_url' => '/store', 'min_view_seconds' => 15]],
        ] as [$name, $keys, $points, $budget, $ad]) {
            $campaign = AdCampaign::query()->create(['name' => $name, 'status' => CampaignStatus::Active, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(2), 'reward_points' => $points, 'point_budget' => $budget]);
            $campaign->placements()->attach(collect($keys)->map(fn ($k) => $placements[$k]));
            Ad::query()->create(['ad_campaign_id' => $campaign->id, ...$ad]);
        }
        app(FeatureFlags::class)->flush();
    }

    /** A small catalogue covering every product type. Demo codes only exist in non-production seeds. */
    private function seedStore(): void
    {
        $cat = fn (string $slug, string $name, int $sort) => Category::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'sort' => $sort]);
        $gift = $cat('gift-cards', 'کارت هدیه', 1);
        $sport = $cat('sport', 'ورزشی', 2);
        $coupons = $cat('coupons', 'کوپن تخفیف', 3);
        $charity = $cat('charity', 'نیکوکاری', 4);
        // Keyed by slug so re-seeding an existing demo database only adds what's missing.
        $product = fn (string $slug, array $attributes) => Product::query()->withTrashed()->firstOrCreate(['slug' => $slug], $attributes + ['is_active' => true]);

        $card = $product('gift-card-500k', ['category_id' => $gift->id, 'name' => 'کارت هدیه ۵۰۰ هزار ریالی', 'type' => ProductType::DigitalCode,
            'summary' => 'قابل استفاده در فروشگاه‌های طرف قرارداد', 'description' => '<p>کد کارت بلافاصله پس از خرید در «سفارش‌های من» نمایش داده می‌شود.</p>',
            'point_price' => 1200, 'max_per_user' => 2, 'sort' => 1]);
        if ($card->wasRecentlyCreated) {
            foreach (range(1, 20) as $i) {
                $code = sprintf('DEMO-%04d-%04d', $card->id, $i);
                ProductCode::query()->create(['product_id' => $card->id, 'code' => $code, 'code_hash' => ProductCode::hashOf($code)]);
            }
            $card->syncCodeStock();
        }

        $product('sport-bottle', ['category_id' => $sport->id, 'name' => 'قمقمه ورزشی ۷۵۰ میلی‌لیتری', 'type' => ProductType::Physical,
            'summary' => 'فولادی، دوجداره', 'description' => '<p>آب را تا ۱۲ ساعت خنک نگه می‌دارد.</p>', 'point_price' => 2500, 'stock' => 40, 'sort' => 2]);
        $product('sport-socks', ['category_id' => $sport->id, 'name' => 'جوراب ورزشی (سه جفت)', 'type' => ProductType::Physical,
            'summary' => 'نخی، کف حوله‌ای، مناسب پیاده‌روی طولانی', 'point_price' => 900, 'stock' => 100, 'min_level' => 3, 'sort' => 3]);
        $product('yoga-mat', ['category_id' => $sport->id, 'name' => 'مت یوگا ۶ میلی‌متری', 'type' => ProductType::Physical,
            'summary' => 'ضدلغزش، همراه بند حمل', 'description' => '<p>مناسب حرکات کششی قبل و بعد از پیاده‌روی.</p>', 'point_price' => 3200, 'stock' => 8, 'min_level' => 2, 'sort' => 4]);
        $product('sport-towel', ['category_id' => $sport->id, 'name' => 'حوله ورزشی میکروفایبر', 'type' => ProductType::Physical,
            'summary' => 'سبک و زودخشک', 'point_price' => 1100, 'stock' => 60, 'sort' => 5]);
        if ($coupon = Coupon::query()->where('title', 'یک کلوچه رایگان')->first()) {
            $product('cafe-cookie', ['category_id' => $coupons->id, 'sponsor_id' => $coupon->sponsor_id, 'coupon_id' => $coupon->id, 'name' => 'کلوچه رایگان کافه قدم',
                'summary' => 'کوپن یک‌بارمصرف در شعبه‌های کافه قدم', 'type' => ProductType::Coupon, 'point_price' => 150, 'max_per_user' => 1, 'sort' => 6]);
        }
        $product('plant-a-tree', ['category_id' => $charity->id, 'name' => 'کاشت یک نهال', 'type' => ProductType::Service,
            'summary' => 'امتیازت به کاشت یک نهال در طرح‌های شهری تبدیل می‌شود', 'point_price' => 500, 'sort' => 7]);

        $this->seedProductImages();
    }

    /** Illustrations under seeders/assets/products named {slug}-{n}.jpg, copied to the public disk. */
    private function seedProductImages(): void
    {
        $files = glob(__DIR__.'/assets/products/*.jpg') ?: [];
        $bySlug = [];
        foreach ($files as $file) {
            if (preg_match('/^(.+)-(\d+)\.jpg$/', basename($file), $m)) {
                $bySlug[$m[1]][(int) $m[2]] = $file;
            }
        }

        $disk = Storage::disk('public');
        foreach (Product::query()->whereIn('slug', array_keys($bySlug))->doesntHave('images')->get() as $product) {
            ksort($bySlug[$product->slug]);
            foreach ($bySlug[$product->slug] as $n => $file) {
                $path = "products/{$product->slug}-{$n}.jpg";
                $disk->put($path, file_get_contents($file));
                $product->images()->create(['path' => $path, 'sort' => $n]);
            }
        }
    }

    /**
     * 30 days of plausible history: background windows through the day plus an
     * occasional active walk. Stored as verified so the demo reflects the
     * post-Phase-3 state; real sessions only get verified by the Fraud Engine.
     */
    private function seedWalkingHistory(User $user, float $fitness): void
    {
        $device = Device::factory()->create(['last_seen_at' => now()]);
        $device->users()->attach($user->id, ['first_seen_at' => now()->subDays(30), 'last_seen_at' => now()]);
        $estimator = app(ActivityEstimator::class);
        $aggregator = app(DailyActivityAggregator::class);
        $sequence = 0;
        $today = CarbonImmutable::now($user->timezone)->startOfDay();

        for ($d = 29; $d >= 0; $d--) {
            $day = $today->subDays($d);
            $windows = [[8, 20, 25], [12, 10, 40], [17, 30, 50], [20, 0, 45]];
            foreach ($windows as [$hour, $minute, $length]) {
                $start = $day->setTime($hour, $minute)->utc();
                if ($start->isFuture()) {
                    continue;
                }
                $steps = (int) round(mt_rand(600, 2600) * $fitness);
                $kind = $hour === 17 && mt_rand(0, 2) === 0 ? SessionKind::Active : SessionKind::Passive;
                $buckets = $kind === SessionKind::Active
                    ? array_map(fn ($m) => ['started_at' => $start->addMinutes($m), 'duration_s' => 60, 'steps' => intdiv($steps, $length)], range(0, $length - 1))
                    : [['started_at' => $start, 'duration_s' => $length * 60, 'steps' => $steps]];
                $steps = array_sum(array_column($buckets, 'steps'));
                $estimate = $estimator->estimate($buckets, $user->profile);

                $session = WalkingSession::query()->create([
                    'user_id' => $user->id,
                    'device_id' => $device->id,
                    'client_session_id' => (string) Str::uuid(),
                    'sequence' => ++$sequence,
                    'payload_hash' => hash('sha256', (string) $sequence),
                    'kind' => $kind,
                    'started_at' => $start,
                    'ended_at' => $start->addMinutes($length),
                    'local_date' => $day->toDateString(),
                    'raw_steps' => $steps,
                    'verified_steps' => $steps,
                    'duration_s' => $length * 60,
                    ...$estimate,
                    'motion_summary' => ['buckets' => count($buckets), 'demo' => true],
                    'confidence_score' => mt_rand(85, 99),
                    'fraud_score' => mt_rand(0, 8),
                    'status' => SessionStatus::Verified,
                    'reward_status' => SessionRewardStatus::None,
                    'scored_at' => $start->addMinutes($length + 1),
                ]);
                $session->samples()->createMany(array_map(fn ($b) => [...$b, 'started_at' => $b['started_at']->toDateTimeString()], $buckets));
            }
            $aggregator->refresh($user, $day->toDateString());
        }
        $device->forceFill(['last_sequence' => $sequence])->save();

        // Real rewards through the engine; holds older than a day are released like the scheduler would.
        $engine = app(RewardEngine::class);
        $wallet = app(WalletService::class);
        $user->walkingSessions()->orderBy('started_at')->each(fn (WalkingSession $s) => $engine->forSession($s));
        $user->forceFill(['leaderboard_visible' => true])->save();
        $progress = app(ProgressService::class);
        $user->walkingSessions()->orderBy('started_at')->each(fn (WalkingSession $s) => $progress->afterSession($s));
        PointTransaction::query()->where('user_id', $user->id)->where('status', TransactionStatus::Pending)->where('created_at', '<', now())
            ->get()
            ->filter(fn (PointTransaction $t) => $t->source_type !== 'walking_session' || WalkingSession::query()->find($t->source_id)?->started_at->lt(now()->subDay()))
            ->each(fn (PointTransaction $t) => $wallet->release($t));
    }
}
