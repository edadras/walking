<?php

namespace Tests\Feature\Map;

use App\Domain\Fraud\FraudEngine;
use App\Domain\Map\RouteMap;
use App\Enums\IntegrityVerdict;
use App\Enums\SessionStatus;
use App\Models\Device;
use App\Models\RouteTrack;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesScoredSessions;
use Tests\TestCase;

class RouteMapTest extends TestCase
{
    use CreatesScoredSessions, RefreshDatabase;

    private User $user;

    private Device $device;

    private CarbonImmutable $start;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 12:00:00', 'Asia/Tehran')->utc());
        $this->seed(PlatformSeeder::class);
        Cache::flush();
        $this->user = User::factory()->create(['share_route' => true]);
        $this->device = Device::factory()->create(['integrity_verdict' => IntegrityVerdict::Device]);
        $this->device->users()->attach($this->user, ['first_seen_at' => now(), 'last_seen_at' => now()]);
        $this->start = CarbonImmutable::parse('2026-09-24 11:00:00', 'Asia/Tehran')->utc();
    }

    private function as(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user->fresh(), 'sanctum');
    }

    /** An "L": 1 km north then 500 m east, a point every ~10 m, 1.3 m/s. */
    private function line(): array
    {
        $pts = [];
        $t = $this->start->addMinute()->getTimestampMs();
        for ($i = 0; $i <= 100; $i++) {
            $pts[] = [35.7 + $i * 0.00009, 51.4, $t + $i * 7700];
        }
        for ($i = 1; $i <= 50; $i++) {
            $pts[] = [35.7 + 100 * 0.00009, 51.4 + $i * 0.00011, $t + (100 + $i) * 7700];
        }

        return $pts;
    }

    private function walk(bool $score = true)
    {
        $s = $this->makeSession($this->user, $this->device, $this->walkingMinutes(22), ['gps_summary' => ['distance_m' => 1500, 'max_speed_mps' => 1.6, 'mock_detected' => false]], $this->start);

        return $score ? app(FraudEngine::class)->score($s)->fresh() : $s;
    }

    public function test_a_shared_walk_appears_anonymously_with_its_ends_cut_off(): void
    {
        $this->walk();
        $this->as($this->user);
        $this->postJson('/api/v1/routes', ['points' => $this->line()])->assertCreated()->assertJsonPath('data.published', true);

        $track = RouteTrack::query()->sole();
        // Straight stretches collapse; the corner survives.
        $this->assertLessThan(8, $track->point_count);
        // Nothing within 150 m of where the walk started or ended.
        $this->assertGreaterThanOrEqual(149, RouteMap::meters([35.7, 51.4], $track->points[0]));
        $points = $track->points;
        $this->assertGreaterThanOrEqual(149, RouteMap::meters([35.709, 51.4055], end($points)));

        $this->app['auth']->forgetGuards();
        $res = $this->getJson('/api/v1/public/map/tracks?bbox=51.3,35.6,51.5,35.8')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($this->user->fresh()->routeColor(), $res->json('data.0.color'));
        $this->assertSame(['color', 'points', 'expires_in'], array_keys($res->json('data.0')));
        $this->assertStringNotContainsString($this->user->public_id, $res->getContent());
    }

    public function test_without_consent_nothing_is_stored(): void
    {
        $this->user->forceFill(['share_route' => false])->save();
        $this->walk();
        $this->as($this->user);
        $this->postJson('/api/v1/routes', ['points' => $this->line()])->assertOk()->assertJsonPath('data.stored', false);
        $this->assertSame(0, RouteTrack::query()->count());
    }

    public function test_a_line_waits_for_its_walk_to_be_verified_and_dies_with_a_rejected_one(): void
    {
        $session = $this->walk(score: false);
        $this->as($this->user);
        $this->postJson('/api/v1/routes', ['points' => $this->line()])->assertCreated()->assertJsonPath('data.published', false);
        $this->getJson('/api/v1/public/map/tracks?bbox=51.3,35.6,51.5,35.8')->assertJsonCount(0, 'data');

        app(FraudEngine::class)->score($session); // listener publishes it
        Cache::flush();
        $this->getJson('/api/v1/public/map/tracks?bbox=51.3,35.6,51.5,35.8')->assertJsonCount(1, 'data');

        RouteTrack::query()->delete();
        $bad = $this->makeSession($this->user, $this->device, $this->walkingMinutes(10), start: $this->start->subHours(2));
        $bad->forceFill(['status' => SessionStatus::Rejected, 'scored_at' => now()])->save();
        $pts = array_map(fn ($p) => [$p[0], $p[1], $p[2] - 7_200_000], array_slice($this->line(), 0, 70));
        $this->postJson('/api/v1/routes', ['points' => $pts])->assertOk()->assertJsonPath('data.stored', false);
        $this->assertSame(0, RouteTrack::query()->count());
    }

    public function test_lines_last_24_hours_after_their_last_point_then_are_deleted(): void
    {
        $this->walk();
        $this->as($this->user);
        $this->postJson('/api/v1/routes', ['points' => $this->line()])->assertCreated();

        CarbonImmutable::setTestNow(now()->addHours(23));
        Cache::flush();
        $this->getJson('/api/v1/public/map/tracks?bbox=51.3,35.6,51.5,35.8')->assertJsonCount(1, 'data');

        CarbonImmutable::setTestNow(now()->addHours(2));
        Cache::flush();
        $this->getJson('/api/v1/public/map/tracks?bbox=51.3,35.6,51.5,35.8')->assertJsonCount(0, 'data');
        $this->artisan('routes:prune')->assertSuccessful();
        $this->assertSame(0, RouteTrack::query()->count());
    }

    public function test_turning_sharing_off_removes_lines_at_once_and_colour_is_choosable(): void
    {
        $this->walk();
        $this->as($this->user);
        $this->patchJson('/api/v1/me/settings', ['route_color' => '#8E4EC6'])->assertOk()->assertJsonPath('data.settings.route_color', '#8E4EC6');
        $this->postJson('/api/v1/routes', ['points' => $this->line()])->assertCreated();
        $this->assertSame('#8E4EC6', RouteTrack::query()->sole()->color);

        $this->patchJson('/api/v1/me/settings', ['route_color' => '#123456'])->assertUnprocessable();
        $this->patchJson('/api/v1/me/settings', ['share_route' => false])->assertOk()->assertJsonPath('data.settings.share_route', false);
        $this->assertSame(0, RouteTrack::query()->count());
    }

    public function test_gps_jumps_are_dropped(): void
    {
        $this->walk();
        $pts = $this->line();
        $pts[60] = [35.9, 51.9, $pts[60][2]]; // 30 km teleport
        $this->as($this->user);
        $this->postJson('/api/v1/routes', ['points' => $pts])->assertCreated();
        $this->assertLessThan(35.71, RouteTrack::query()->sole()->max_lat);
    }

    public function test_bbox_is_validated_and_the_web_map_renders(): void
    {
        $this->getJson('/api/v1/public/map/tracks?bbox=abc')->assertUnprocessable();
        $this->getJson('/api/v1/public/map/tracks?bbox=40,20,60,40')->assertStatus(422);
        $this->get('/map')->assertOk()->assertSee('نقشه گام‌یار')->assertSee('/api/v1/public/map/tracks', false);
    }
}
