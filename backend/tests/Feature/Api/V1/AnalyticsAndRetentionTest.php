<?php

namespace Tests\Feature\Api\V1;

use App\Models\ActivitySample;
use App\Models\AnalyticsEvent;
use App\Models\WalkingSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWalkingSessions;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class AnalyticsAndRetentionTest extends TestCase
{
    use BuildsWalkingSessions, RefreshDatabase, SignsDeviceRequests;

    public function test_analytics_strips_personal_data_and_unknown_events(): void
    {
        $this->loginAs();

        $this->authedJson('POST', '/api/v1/analytics/events', ['events' => [
            ['name' => 'app_open', 'occurred_at' => now()->toIso8601String(), 'properties' => ['screen' => 'home', 'phone' => '0912', 'lat' => 35.7, 'Bad-Key' => 1, 'nested' => ['x' => 1]]],
        ]])->assertNoContent();

        $event = AnalyticsEvent::query()->firstOrFail();
        $this->assertSame(['screen' => 'home'], $event->properties);
        $this->assertNotNull($event->device_id);

        $this->authedJson('POST', '/api/v1/analytics/events', ['events' => [['name' => 'steal_data', 'occurred_at' => now()->toIso8601String()]]])
            ->assertStatus(422);
    }

    public function test_retention_prunes_old_minute_samples_but_keeps_sessions(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 10:00:00', 'Asia/Tehran')->utc());
        $this->loginAs();
        $this->signedJson('POST', '/api/v1/walking-sessions', $this->activeSession(minutes: 5))->assertStatus(202);

        $this->travel(31)->days();
        CarbonImmutable::setTestNow(now()->toImmutable());
        $this->artisan('retention:prune')->assertSuccessful();

        $this->assertSame(0, ActivitySample::query()->count());
        $this->assertSame(1, WalkingSession::query()->count());
    }
}
