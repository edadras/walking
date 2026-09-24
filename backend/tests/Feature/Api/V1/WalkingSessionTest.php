<?php

namespace Tests\Feature\Api\V1;

use App\Events\WalkingSessionSubmitted;
use App\Models\ActivitySample;
use App\Models\DailyActivity;
use App\Models\User;
use App\Models\WalkingSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BuildsWalkingSessions;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class WalkingSessionTest extends TestCase
{
    use BuildsWalkingSessions, RefreshDatabase, SignsDeviceRequests;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // 10:00 in Tehran — far from local midnight.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 10:00:00', 'Asia/Tehran')->utc());
        $this->user = $this->loginAs();
        Event::fake([WalkingSessionSubmitted::class]);
    }

    public function test_accepts_a_session_and_derives_estimates_server_side(): void
    {
        $response = $this->signedJson('POST', '/api/v1/walking-sessions', $this->activeSession(minutes: 10, perMinute: 110))
            ->assertStatus(202)
            ->assertJsonPath('data.steps', 1100)
            ->assertJsonPath('data.verified_steps', null)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.local_date', '2026-09-24')
            ->assertJsonPath('meta.duplicate', false);

        $this->assertGreaterThan(700, $response->json('data.distance_m'));
        $this->assertGreaterThan(0, $response->json('data.calories_kcal'));
        $this->assertSame(10, ActivitySample::query()->count());

        $daily = DailyActivity::query()->where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame(1100, $daily->raw_steps);
        $this->assertSame(0, $daily->verified_steps);
        $this->assertSame(7500, $daily->goal_steps);

        Event::assertDispatched(WalkingSessionSubmitted::class);
    }

    public function test_client_cannot_set_distance_calories_or_verified_steps(): void
    {
        $payload = $this->activeSession() + ['verified_steps' => 99999, 'distance_m' => 99999, 'calories_kcal' => 9999, 'status' => 'verified'];

        $this->signedJson('POST', '/api/v1/walking-sessions', $payload)->assertStatus(202);

        $session = WalkingSession::query()->firstOrFail();
        $this->assertNull($session->verified_steps);
        $this->assertLessThan(5000, $session->distance_m);
        $this->assertSame('submitted', $session->status->value);
    }

    public function test_resubmitting_the_same_session_is_idempotent(): void
    {
        $payload = $this->activeSession();

        $first = $this->signedJson('POST', '/api/v1/walking-sessions', $payload)->assertStatus(202);
        $second = $this->signedJson('POST', '/api/v1/walking-sessions', $payload)->assertOk()->assertJsonPath('meta.duplicate', true);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, WalkingSession::query()->count());
        $this->assertSame(1100, DailyActivity::query()->value('raw_steps'));
        Event::assertDispatchedTimes(WalkingSessionSubmitted::class, 1);
    }

    public function test_same_id_with_different_content_is_a_conflict(): void
    {
        $payload = $this->activeSession();
        $this->signedJson('POST', '/api/v1/walking-sessions', $payload)->assertStatus(202);

        $payload['raw_steps'] += 10;
        $payload['buckets'][0]['steps'] += 10;

        $this->signedJson('POST', '/api/v1/walking-sessions', $payload)
            ->assertStatus(409)->assertJsonPath('error.code', 'session_conflict');
    }

    public function test_replayed_or_reordered_sequence_is_rejected(): void
    {
        $this->signedJson('POST', '/api/v1/walking-sessions', $this->activeSession(end: CarbonImmutable::now()->subHour(), overrides: ['sequence' => 5]))->assertStatus(202);

        // Fresh client id but an old sequence number: replay of captured data.
        $this->signedJson('POST', '/api/v1/walking-sessions', $this->activeSession(overrides: ['sequence' => 5]))
            ->assertStatus(409)->assertJsonPath('error.code', 'sequence_replayed');
        $this->signedJson('POST', '/api/v1/walking-sessions', $this->activeSession(overrides: ['sequence' => 3]))
            ->assertStatus(409);

        // Gaps are fine (a session may have been lost on the device).
        $this->signedJson('POST', '/api/v1/walking-sessions', $this->activeSession(overrides: ['sequence' => 9]))->assertStatus(202);
    }

    public function test_requires_a_signed_request_from_the_tokens_device(): void
    {
        $this->authedJson('POST', '/api/v1/walking-sessions', $this->activeSession())
            ->assertStatus(401)->assertJsonPath('error.code', 'device_not_registered');
        $this->assertSame(0, WalkingSession::query()->count());
    }

    public function test_rejects_sessions_in_the_future_or_too_old(): void
    {
        $this->signedJson('POST', '/api/v1/walking-sessions', $this->activeSession(end: CarbonImmutable::now()->addMinutes(30)))
            ->assertStatus(422)->assertJsonPath('error.code', 'session_in_future');

        $this->signedJson('POST', '/api/v1/walking-sessions', $this->activeSession(end: CarbonImmutable::now()->subDays(8)))
            ->assertStatus(422)->assertJsonPath('error.code', 'session_too_old');
    }

    public function test_session_may_not_cross_local_midnight(): void
    {
        $end = CarbonImmutable::parse('2026-09-24 00:05:00', 'Asia/Tehran')->utc();

        $this->signedJson('POST', '/api/v1/walking-sessions', $this->activeSession(minutes: 10, end: $end))
            ->assertStatus(422)->assertJsonPath('error.code', 'session_crosses_midnight');
    }

    public function test_offline_session_counts_towards_its_own_day(): void
    {
        $yesterday = CarbonImmutable::parse('2026-09-23 18:00:00', 'Asia/Tehran')->utc();

        $this->signedJson('POST', '/api/v1/walking-sessions', $this->activeSession(end: $yesterday))
            ->assertStatus(202)->assertJsonPath('data.local_date', '2026-09-23');
    }

    public function test_buckets_must_add_up_and_stay_physically_possible(): void
    {
        $mismatch = $this->activeSession();
        $mismatch['raw_steps'] += 500;
        $this->signedJson('POST', '/api/v1/walking-sessions', $mismatch)->assertStatus(422)->assertJsonPath('error.code', 'session_invalid');

        $impossible = $this->activeSession(minutes: 2, perMinute: 400);
        $this->signedJson('POST', '/api/v1/walking-sessions', $impossible)->assertStatus(422)->assertJsonPath('error.code', 'session_invalid');

        $outside = $this->activeSession(minutes: 3);
        $outside['buckets'][0]['started_at'] = CarbonImmutable::parse($outside['started_at'])->subMinutes(5)->toIso8601String();
        $this->signedJson('POST', '/api/v1/walking-sessions', $outside)->assertStatus(422);

        $overlapping = $this->activeSession(minutes: 3);
        $overlapping['buckets'][1]['started_at'] = $overlapping['buckets'][0]['started_at'];
        $this->signedJson('POST', '/api/v1/walking-sessions', $overlapping)->assertStatus(422);

        $this->assertSame(0, WalkingSession::query()->count());
    }

    public function test_batch_reports_each_item_and_applies_in_sequence_order(): void
    {
        $base = CarbonImmutable::now()->subHours(3);
        $a = $this->passiveSession(800, $base);
        $b = $this->passiveSession(1200, $base->addMinutes(30));
        $bad = $this->passiveSession(100, $base->addMinutes(60));
        $bad['raw_steps'] = 999;

        $response = $this->signedJson('POST', '/api/v1/walking-sessions/batch', ['sessions' => [$b, $bad, $a]])->assertOk();
        $byId = collect($response->json('data'))->keyBy('client_session_id');

        $this->assertSame('accepted', $byId[$a['client_session_id']]['status']);
        $this->assertSame('accepted', $byId[$b['client_session_id']]['status']);
        $this->assertSame('rejected', $byId[$bad['client_session_id']]['status']);
        $this->assertSame('session_invalid', $byId[$bad['client_session_id']]['error']['code']);
        $this->assertSame(2000, DailyActivity::query()->value('raw_steps'));

        // Retrying the whole backlog is harmless.
        $retry = $this->signedJson('POST', '/api/v1/walking-sessions/batch', ['sessions' => [$a, $b]])->assertOk();
        $this->assertSame(['duplicate', 'duplicate'], array_column($retry->json('data'), 'status'));
        $this->assertSame(2000, DailyActivity::query()->value('raw_steps'));
    }

    public function test_overlap_with_another_device_is_recorded(): void
    {
        $payload = $this->activeSession(minutes: 20);
        $this->signedJson('POST', '/api/v1/walking-sessions', $payload)->assertStatus(202);

        // Same account, second phone, same 20 minutes.
        $this->device = null;
        $this->deviceKey = $this->newDeviceKey();
        $this->travel(1)->minutes();
        $this->loginAs();
        $this->travelBack();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 10:00:00', 'Asia/Tehran')->utc());
        $this->nextSequence = 1;

        $second = $this->activeSession(minutes: 20);
        $this->signedJson('POST', '/api/v1/walking-sessions', $second)->assertStatus(202);

        $this->assertSame(1200, WalkingSession::query()->latest('id')->value('overlap_s'));
    }

    public function test_users_only_see_their_own_sessions(): void
    {
        $id = $this->signedJson('POST', '/api/v1/walking-sessions', $this->activeSession())->json('data.id');
        $this->authedJson('GET', "/api/v1/walking-sessions/$id")->assertOk()->assertJsonCount(10, 'data.samples');
        $this->authedJson('GET', '/api/v1/walking-sessions')->assertOk()->assertJsonCount(1, 'data');

        $this->device = null;
        $this->deviceKey = $this->newDeviceKey();
        $this->travel(2)->minutes();
        $this->loginAs('09350000000');

        $this->authedJson('GET', "/api/v1/walking-sessions/$id")->assertNotFound();
        $this->authedJson('GET', '/api/v1/walking-sessions')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_home_day_and_daily_endpoints(): void
    {
        $morning = CarbonImmutable::parse('2026-09-24 08:20:00', 'Asia/Tehran')->utc();
        $this->signedJson('POST', '/api/v1/walking-sessions', $this->activeSession(minutes: 10, perMinute: 124, end: $morning->addMinutes(10)))->assertStatus(202);
        $this->signedJson('POST', '/api/v1/walking-sessions', $this->passiveSession(3000, CarbonImmutable::parse('2026-09-22 12:00:00', 'Asia/Tehran')->utc()))->assertStatus(202);

        $this->authedJson('GET', '/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.today.steps', 1240)
            ->assertJsonPath('data.today.pending_steps', 1240)
            ->assertJsonPath('data.today.goal', 7500)
            ->assertJsonCount(7, 'data.week')
            ->assertJsonPath('data.week.4.steps', 3000);

        $day = $this->authedJson('GET', '/api/v1/activity/day')->assertOk()->json('data');
        $this->assertSame(1240, $day['hourly'][8]);
        $this->assertSame(1240, array_sum($day['hourly']));
        $this->assertCount(1, $day['sessions']);

        $daily = $this->authedJson('GET', '/api/v1/activity/daily?from=2026-09-20&to=2026-09-24')->assertOk()->json('data');
        $this->assertCount(5, $daily);
        $this->assertSame(0, $daily[0]['steps']);
        $this->assertSame(3000, $daily[2]['steps']);

        $this->authedJson('GET', '/api/v1/activity/daily?from=2026-09-20&to=2026-09-30')->assertStatus(422);
    }

    public function test_home_cache_refreshes_after_new_session(): void
    {
        $this->authedJson('GET', '/api/v1/home')->assertJsonPath('data.today.steps', 0);
        $this->signedJson('POST', '/api/v1/walking-sessions', $this->activeSession())->assertStatus(202);
        $this->authedJson('GET', '/api/v1/home')->assertJsonPath('data.today.steps', 1100);
    }
}
