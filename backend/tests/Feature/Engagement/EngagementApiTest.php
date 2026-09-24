<?php

namespace Tests\Feature\Engagement;

use App\Domain\Leaderboard\LeaderboardService;
use App\Domain\Notification\PushSender;
use App\Domain\Referral\ReferralService;
use App\Enums\ChallengeStatus;
use App\Enums\ChallengeType;
use App\Enums\IntegrityVerdict;
use App\Jobs\SendOtpSms;
use App\Models\Challenge;
use App\Models\DailyActivity;
use App\Models\Device;
use App\Models\PointTransaction;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\UserNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;
use Tests\Concerns\BuildsWalkingSessions;
use Tests\Concerns\CreatesScoredSessions;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class EngagementApiTest extends TestCase
{
    use BuildsWalkingSessions, CreatesScoredSessions, RefreshDatabase, SignsDeviceRequests;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 12:00:00', 'Asia/Tehran')->utc());
        $this->seed(PlatformSeeder::class);
    }

    private function verifiedDay(User $user, string $date, int $steps): void
    {
        DailyActivity::query()->updateOrCreate(['user_id' => $user->id, 'local_date' => $date], ['verified_steps' => $steps, 'raw_steps' => $steps, 'goal_steps' => 7500]);
    }

    private function realWalk(int $minutes): array
    {
        $p = $this->activeSession(minutes: $minutes, end: CarbonImmutable::now()->subMinutes(5));
        foreach ($p['buckets'] as $i => &$b) {
            $b['steps'] = 100 + ($i * 7) % 13;
            $b['detector_steps'] = $b['steps'];
        }
        $p['raw_steps'] = array_sum(array_column($p['buckets'], 'steps'));

        return $p;
    }

    public function test_water_tracker(): void
    {
        $this->loginAs();

        $this->authedJson('POST', '/api/v1/health/water', ['amount_ml' => 250])->assertCreated()->assertJsonPath('data.total_ml', 250)->assertJsonPath('data.goal_glasses', 8);
        $id = $this->authedJson('POST', '/api/v1/health/water', ['amount_ml' => 500])->json('data.logs.1.id');
        $this->authedJson('POST', '/api/v1/health/water', ['amount_ml' => 5000])->assertStatus(422);
        $this->authedJson('DELETE', "/api/v1/health/water/$id")->assertNoContent();
        $this->authedJson('GET', '/api/v1/health/water')->assertJsonPath('data.total_ml', 250)->assertJsonPath('data.glasses', 1);
    }

    public function test_water_suggestion_uses_weight_and_is_approximate(): void
    {
        $this->loginAs();
        $this->authedJson('PATCH', '/api/v1/me', ['weight_kg' => 70]);

        $this->assertSame(2250, $this->authedJson('GET', '/api/v1/health/water')->json('data.suggested_goal_ml'));
    }

    public function test_health_summary_and_weekly_report(): void
    {
        $user = $this->loginAs();
        // This week (Sat 20 → Thu 24): 8000 + 6000; last week same days: 10000.
        $this->verifiedDay($user, '2026-09-20', 8000);
        $this->verifiedDay($user, '2026-09-23', 6000);
        $this->verifiedDay($user, '2026-09-14', 10000);

        $summary = $this->authedJson('GET', '/api/v1/health/summary?range=week')->assertOk()->json('data');
        $this->assertCount(7, $summary['days']);
        $this->assertSame(14000, $summary['totals']['steps']);
        $this->assertSame(2000, $summary['averages']['daily_steps']);

        $this->authedJson('GET', '/api/v1/activity/weekly-report')
            ->assertJsonPath('data.week_start', '2026-09-19')
            ->assertJsonPath('data.steps', 14000)
            ->assertJsonPath('data.previous_steps', 10000)
            ->assertJsonPath('data.change_percent', 40);
    }

    public function test_leaderboard_ranks_verified_steps_and_respects_privacy(): void
    {
        $me = $this->loginAs();
        $ali = User::factory()->create(['display_name' => 'علی']);
        $sara = User::factory()->create(['display_name' => 'سارا']);
        $hidden = User::factory()->create(['display_name' => 'پنهان', 'leaderboard_visible' => false]);
        $this->verifiedDay($ali, '2026-09-23', 12000);
        $this->verifiedDay($sara, '2026-09-24', 9000);
        $this->verifiedDay($hidden, '2026-09-24', 50000);
        $this->verifiedDay($me, '2026-09-24', 7000);

        $board = $this->authedJson('GET', '/api/v1/leaderboard?period=week')->assertOk()->json('data');

        $this->assertSame(['علی', 'سارا', $me->publicName()], array_column($board['entries'], 'name'));
        $this->assertSame(3, $board['me']['rank']);
        $this->assertTrue($board['entries'][2]['is_me']);
        $this->assertArrayNotHasKey('phone', $board['entries'][0]);

        $this->assertSame(['سارا', $me->publicName()], array_column($this->authedJson('GET', '/api/v1/leaderboard?period=day')->json('data.entries'), 'name'));
    }

    public function test_redis_leaderboard_sync_and_hide(): void
    {
        config(['walk.leaderboard.driver' => 'redis']);
        Redis::flushdb();
        $me = $this->loginAs();
        $other = User::factory()->create(['display_name' => 'رقیب']);
        $this->verifiedDay($me, '2026-09-24', 7000);
        $this->verifiedDay($other, '2026-09-24', 9000);
        $boards = app(LeaderboardService::class);
        $boards->sync($me, '2026-09-24');
        $boards->sync($other, '2026-09-24');

        $this->authedJson('GET', '/api/v1/leaderboard?period=day')->assertJsonPath('data.me.rank', 2)->assertJsonPath('data.entries.0.name', 'رقیب');

        $this->authedJson('PATCH', '/api/v1/me/settings', ['leaderboard_visible' => false])->assertOk();
        $this->authedJson('GET', '/api/v1/leaderboard?period=day')->assertJsonPath('data.me', null)->assertJsonCount(1, 'data.entries');
        Redis::flushdb();
    }

    public function test_challenge_join_progress_and_single_reward(): void
    {
        $user = $this->loginAs();
        $this->device->forceFill(['integrity_verdict' => IntegrityVerdict::Device])->save();
        $challenge = Challenge::query()->create([
            'title' => 'چالش ۳ هزار', 'type' => ChallengeType::Steps, 'metric' => 'steps', 'target_value' => 3000,
            'reward_points' => 150, 'reward_xp' => 40, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3), 'status' => ChallengeStatus::Active,
        ]);

        $this->authedJson('POST', "/api/v1/challenges/{$challenge->public_id}/join")->assertStatus(401); // must be signed
        $this->signedJson('POST', "/api/v1/challenges/{$challenge->public_id}/join")->assertCreated()->assertJsonPath('data.joined', true)->assertJsonPath('data.progress', 0);
        // Only activity after joining counts: walk later.
        $this->travel(1)->hours();

        $this->signedJson('POST', '/api/v1/walking-sessions', $this->realWalk(20))->assertStatus(202);
        $this->authedJson('GET', "/api/v1/challenges/{$challenge->public_id}")->assertJsonPath('data.completed_at', null);

        $this->travel(1)->hours(); // a separate, later walk (overlapping time would not count twice)
        $this->signedJson('POST', '/api/v1/walking-sessions', $this->realWalk(15))->assertStatus(202);
        $data = $this->authedJson('GET', "/api/v1/challenges/{$challenge->public_id}")->json('data');
        $this->assertNotNull($data['completed_at']);
        $this->assertSame(1, PointTransaction::query()->where('type', 'challenge_reward')->count());
        $this->assertSame(150, (int) PointTransaction::query()->where('type', 'challenge_reward')->value('amount'));

        $this->authedJson('GET', '/api/v1/home')->assertJsonPath('data.challenge', null);
    }

    public function test_closed_or_full_challenges_cannot_be_joined(): void
    {
        $this->loginAs();
        $full = Challenge::query()->create(['title' => 'پر', 'type' => ChallengeType::Steps, 'metric' => 'steps', 'target_value' => 1000, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'status' => ChallengeStatus::Active, 'max_participants' => 1, 'participants_count' => 1]);
        $ended = Challenge::query()->create(['title' => 'تمام', 'type' => ChallengeType::Steps, 'metric' => 'steps', 'target_value' => 1000, 'starts_at' => now()->subDays(5), 'ends_at' => now()->subDay(), 'status' => ChallengeStatus::Active]);

        $this->signedJson('POST', "/api/v1/challenges/{$full->public_id}/join")->assertStatus(422)->assertJsonPath('error.code', 'challenge_closed');
        $this->signedJson('POST', "/api/v1/challenges/{$ended->public_id}/join")->assertStatus(422);
    }

    public function test_referral_pays_both_after_qualifying_steps(): void
    {
        $referrer = User::factory()->create(['referral_code' => 'FRIEND7']);
        $referee = $this->loginWithReferral('FRIEND7');
        $this->assertSame('pending', Referral::query()->sole()->status);

        $this->verifiedDay($referee, '2026-09-24', 5200);
        app(ReferralService::class)->evaluate($referee);

        $this->assertSame('rewarded', Referral::query()->sole()->status);
        $this->assertSame(200, (int) PointTransaction::query()->where('user_id', $referrer->id)->sum('amount'));
        $this->assertSame(100, (int) PointTransaction::query()->where('user_id', $referee->id)->sum('amount'));
        $this->authedJson('GET', '/api/v1/referral')->assertJsonPath('data.code', $referee->referral_code);
    }

    public function test_referral_from_the_same_device_is_rejected(): void
    {
        $referrer = User::factory()->create(['referral_code' => 'SAMEPH1']);
        $referee = $this->loginWithReferral('SAMEPH1');
        $this->device->users()->attach($referrer, ['first_seen_at' => now(), 'last_seen_at' => now()]);

        $this->verifiedDay($referee, '2026-09-24', 9000);
        app(ReferralService::class)->evaluate($referee);

        $this->assertSame('shared_device', Referral::query()->sole()->rejection_reason);
        $this->assertSame(0, PointTransaction::query()->count());
    }

    public function test_notifications_inbox_and_push_preferences(): void
    {
        $sent = [];
        $this->app->instance(PushSender::class, new class($sent) implements PushSender
        {
            public function __construct(public array &$sent) {}

            public function send(Device $device, string $title, string $body, array $data = []): void
            {
                $this->sent[] = $title;
            }
        });
        $user = $this->loginAs();
        $this->authedJson('PUT', '/api/v1/devices/push-token', ['provider' => 'fcm', 'token' => 'tok-1'])->assertNoContent();

        $user->notify(new UserNotification('challenge', 'چالش جدید', 'بدن'));
        $this->authedJson('PATCH', '/api/v1/me/notification-preferences', ['preferences' => ['challenge' => false]]);
        $user->notify(new UserNotification('challenge', 'چالش دوم', 'بدن'));

        $this->assertSame(['چالش جدید'], $sent); // second one not pushed…
        $inbox = $this->authedJson('GET', '/api/v1/notifications')->assertOk();
        $this->assertCount(2, $inbox->json('data')); // …but still in the inbox
        $this->assertSame(2, $inbox->json('meta.unread'));

        $this->authedJson('POST', '/api/v1/notifications/read')->assertJsonPath('data.unread', 0);
    }

    public function test_quiet_hours_suppress_push(): void
    {
        $sent = [];
        $this->app->instance(PushSender::class, new class($sent) implements PushSender
        {
            public function __construct(public array &$sent) {}

            public function send(Device $device, string $title, string $body, array $data = []): void
            {
                $this->sent[] = $title;
            }
        });
        $user = $this->loginAs();
        $this->authedJson('PUT', '/api/v1/devices/push-token', ['provider' => 'fcm', 'token' => 'tok-1']);
        $this->authedJson('PATCH', '/api/v1/me/settings', ['quiet_hours_start' => '11:00', 'quiet_hours_end' => '13:00']);

        $user->fresh()->notify(new UserNotification('announcement', 'اطلاعیه', 'بدن'));

        $this->assertSame([], $sent);
    }

    private function loginWithReferral(string $code): User
    {
        $this->registerDevice()->assertSuccessful();
        Bus::fake([SendOtpSms::class]);
        $this->signedJson('POST', '/api/v1/auth/otp/request', ['phone' => '09127777777'])->assertOk();
        $otp = null;
        Bus::assertDispatched(SendOtpSms::class, function ($job) use (&$otp) {
            $otp = $job->code;

            return true;
        });
        $r = $this->signedJson('POST', '/api/v1/auth/otp/verify', ['phone' => '09127777777', 'code' => $otp, 'referral_code' => $code])->assertOk();
        $this->token = $r->json('data.token');

        return User::query()->where('public_id', $r->json('data.user.id'))->firstOrFail();
    }
}
