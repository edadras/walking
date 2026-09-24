<?php

namespace Database\Seeders;

use App\Domain\Activity\ActivityEstimator;
use App\Domain\Activity\DailyActivityAggregator;
use App\Domain\Gamification\ProgressService;
use App\Domain\Reward\RewardEngine;
use App\Domain\Wallet\WalletService;
use App\Enums\AdminRole;
use App\Enums\ChallengeStatus;
use App\Enums\ChallengeType;
use App\Enums\SessionKind;
use App\Enums\SessionRewardStatus;
use App\Enums\SessionStatus;
use App\Enums\TransactionStatus;
use App\Models\Admin;
use App\Models\Challenge;
use App\Models\Device;
use App\Models\PointTransaction;
use App\Models\User;
use App\Models\WalkingSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
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
