<?php

namespace Tests\Feature\Sponsor;

use App\Domain\Sponsor\QrToken;
use App\Enums\LocationStatus;
use App\Enums\SponsorStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\VerificationMethod;
use App\Enums\VisitStatus;
use App\Models\FraudEvent;
use App\Models\PointTransaction;
use App\Models\UserCoupon;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSponsorOffers;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class VisitFlowTest extends TestCase
{
    use CreatesSponsorOffers, RefreshDatabase, SignsDeviceRequests;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 12:00:00', 'Asia/Tehran')->utc());
        $this->seed(PlatformSeeder::class);
    }

    /** A different person on their own phone. */
    private function asNewPhone(string $phone): void
    {
        $this->device = null;
        $this->deviceKey = null;
        $this->loginAs($phone);
    }

    /** @return array{0: mixed, 1: mixed, 2: ?string} */
    private function phone(): array
    {
        return [$this->deviceKey, $this->device, $this->token];
    }

    private function usePhone(array $phone): void
    {
        [$this->deviceKey, $this->device, $this->token] = $phone;
        $this->app['auth']->forgetGuards();
    }

    private function start($campaign, $location, array $fix)
    {
        return $this->signedJson('POST', '/api/v1/visits', ['campaign_id' => $campaign->public_id, 'location_id' => $location->public_id, ...$fix]);
    }

    private function ping(string $visitId, array $fix, int $afterSeconds = 30)
    {
        $this->travel($afterSeconds)->seconds();

        return $this->signedJson('POST', "/api/v1/visits/{$visitId}/ping", $fix);
    }

    public function test_stay_is_measured_with_the_server_clock_and_rewards_once(): void
    {
        $user = $this->loginAs();
        $sponsor = $this->sponsor();
        $location = $this->location($sponsor);
        $coupon = $this->coupon($sponsor);
        $campaign = $this->campaign($sponsor, $location, ['coupon_id' => $coupon->id]);

        $id = $this->start($campaign, $location, $this->fix())->assertCreated()->assertJsonPath('data.status', 'started')->json('data.id');

        // Spamming pings adds nothing: only real elapsed server time counts.
        $this->ping($id, $this->fix(), 2)->assertJsonPath('data.stay_seconds', 0);
        $this->ping($id, $this->fix(), 28)->assertJsonPath('data.stay_seconds', 30);
        $this->ping($id, $this->fix(15), 30)->assertJsonPath('data.stay_seconds', 60);
        $this->ping($id, $this->fix(), 30);
        $done = $this->ping($id, $this->fix(), 30)->assertOk();

        $done->assertJsonPath('data.status', 'rewarded')->assertJsonPath('data.points_awarded', 50)
            ->assertJsonPath('data.coupon.status', 'available');

        $tx = PointTransaction::query()->where('user_id', $user->id)->sole();
        $this->assertSame(TransactionType::SponsorReward, $tx->type);
        $this->assertSame(TransactionStatus::Pending, $tx->status, 'sponsor rewards go through the same review hold');
        $this->assertSame(50, $campaign->fresh()->points_spent);
        $this->assertSame(1, $campaign->fresh()->rewards_count);
        $this->assertSame(50, $sponsor->fresh()->points_spent);

        $visit = Visit::query()->where('public_id', $id)->sole();
        $this->assertNull($visit->last_lat, 'the last position is erased once the visit closes');
        $this->assertSame(1, $coupon->fresh()->claimed_count);

        // Further pings do nothing; a new visit is refused (limit 1 per user).
        $this->ping($id, $this->fix(), 30)->assertJsonPath('data.status', 'rewarded');
        $this->start($campaign, $location, $this->fix())->assertStatus(409)->assertJsonPath('error.code', 'limit_reached');
        $this->assertSame(1, PointTransaction::query()->where('user_id', $user->id)->count());
    }

    public function test_the_geofence_rejects_far_inaccurate_and_mocked_positions(): void
    {
        $user = $this->loginAs();
        $sponsor = $this->sponsor();
        $location = $this->location($sponsor);
        $campaign = $this->campaign($sponsor, $location);

        $this->start($campaign, $location, $this->fix(400))->assertStatus(422)->assertJsonPath('error.code', 'outside_geofence');
        // A bad fix cannot stretch the fence: 60 m radius + at most 30 m of GPS error.
        $this->start($campaign, $location, $this->fix(95, 70))->assertStatus(422)->assertJsonPath('error.code', 'outside_geofence');
        $this->start($campaign, $location, $this->fix(10, 200))->assertStatus(422)->assertJsonPath('error.code', 'poor_accuracy');
        $this->start($campaign, $location, $this->fix(10, 10, mock: true))->assertStatus(422)->assertJsonPath('error.code', 'mock_location');

        $this->assertTrue(FraudEvent::query()->where('user_id', $user->id)->where('rule_key', 'visit_mock_location')->exists());
        $this->assertSame(0, Visit::query()->count());
    }

    public function test_only_consecutive_inside_pings_count_toward_the_stay(): void
    {
        $this->loginAs();
        $sponsor = $this->sponsor();
        $location = $this->location($sponsor);
        $campaign = $this->campaign($sponsor, $location);

        $id = $this->start($campaign, $location, $this->fix())->json('data.id');
        $this->ping($id, $this->fix(300))->assertJsonPath('data.stay_seconds', 0)->assertJsonPath('data.inside', false);
        // Coming back: the time spent outside is not credited, the next interval is.
        $this->ping($id, $this->fix())->assertJsonPath('data.stay_seconds', 0)->assertJsonPath('data.inside', true);
        $this->ping($id, $this->fix())->assertJsonPath('data.stay_seconds', 30);
        // Going silent for 10 minutes (app killed, phone left behind…) is not presence.
        $this->ping($id, $this->fix(), 600)->assertJsonPath('data.stay_seconds', 30);
    }

    public function test_teleporting_between_pings_rejects_the_visit(): void
    {
        $user = $this->loginAs();
        $sponsor = $this->sponsor();
        $location = $this->location($sponsor);
        $campaign = $this->campaign($sponsor, $location);

        $id = $this->start($campaign, $location, $this->fix())->json('data.id');
        $this->ping($id, $this->fix(5000))->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.rejection_reason', 'teleport');
        $this->assertTrue(FraudEvent::query()->where('user_id', $user->id)->where('rule_key', 'visit_teleport')->exists());
    }

    public function test_rotating_qr_is_required_verified_and_short_lived(): void
    {
        $this->loginAs();
        $sponsor = $this->sponsor();
        $location = $this->location($sponsor);
        $other = $this->location($sponsor, ['name' => 'شعبه دیگر']);
        $campaign = $this->campaign($sponsor, $location, ['verification_method' => VerificationMethod::GeofenceQr]);
        $qr = app(QrToken::class);

        $id = $this->start($campaign, $location, $this->fix())->json('data.id');
        foreach (range(1, 4) as $_) {
            $this->ping($id, $this->fix());
        }
        $this->signedJson('GET', "/api/v1/visits/{$id}")->assertJsonPath('data.status', 'started')->assertJsonPath('data.stay_seconds', 120);

        $old = $qr->issue($location, now()->subSeconds(90));
        $this->signedJson('POST', "/api/v1/visits/{$id}/qr", ['token' => $old])->assertStatus(422)->assertJsonPath('error.code', 'qr_expired');
        $this->signedJson('POST', "/api/v1/visits/{$id}/qr", ['token' => $qr->issue($other)])->assertStatus(422)->assertJsonPath('error.code', 'qr_wrong_location');
        $forged = substr($qr->issue($location), 0, -3).'AAA';
        $this->signedJson('POST', "/api/v1/visits/{$id}/qr", ['token' => $forged])->assertStatus(422)->assertJsonPath('error.code', 'qr_invalid');

        $this->signedJson('POST', "/api/v1/visits/{$id}/qr", ['token' => $qr->issue($location)])->assertOk()
            ->assertJsonPath('data.status', 'rewarded')->assertJsonPath('data.qr_verified', true);
    }

    public function test_qr_must_be_scanned_while_present(): void
    {
        $this->loginAs();
        $sponsor = $this->sponsor();
        $location = $this->location($sponsor);
        $campaign = $this->campaign($sponsor, $location, ['verification_method' => VerificationMethod::GeofenceQr]);

        $id = $this->start($campaign, $location, $this->fix())->json('data.id');
        $this->ping($id, $this->fix(250));
        $this->signedJson('POST', "/api/v1/visits/{$id}/qr", ['token' => app(QrToken::class)->issue($location)])
            ->assertStatus(422)->assertJsonPath('error.code', 'not_present');
    }

    public function test_several_customers_can_scan_the_same_screen(): void
    {
        $sponsor = $this->sponsor();
        $location = $this->location($sponsor);
        $campaign = $this->campaign($sponsor, $location, ['verification_method' => VerificationMethod::GeofenceQr, 'min_stay_seconds' => 30]);

        $people = [];
        foreach (['09121111111', '09122222222'] as $phone) {
            $this->asNewPhone($phone);
            $people[] = [$this->phone(), $this->start($campaign, $location, $this->fix())->json('data.id')];
        }
        foreach ($people as [$phone, $id]) {
            $this->usePhone($phone);
            // Stay is met, but nothing is paid without the branch's QR.
            $this->ping($id, $this->fix())->assertJsonPath('data.status', 'started');
        }

        $token = app(QrToken::class)->issue($location);
        foreach ($people as [$phone, $id]) {
            $this->usePhone($phone);
            $this->signedJson('POST', "/api/v1/visits/{$id}/qr", ['token' => $token])->assertJsonPath('data.status', 'rewarded');
        }
    }

    public function test_budget_is_never_overspent(): void
    {
        $sponsor = $this->sponsor(budget: 1_000);
        $location = $this->location($sponsor);
        $campaign = $this->campaign($sponsor, $location, ['point_budget' => 80, 'reward_points' => 50, 'min_stay_seconds' => 30]);

        // Both start while the budget still covers one more reward…
        $people = [];
        foreach (['09121111111', '09122222222'] as $phone) {
            $this->asNewPhone($phone);
            $people[] = [$this->phone(), $this->start($campaign, $location, $this->fix())->assertCreated()->json('data.id')];
        }
        // …but only one of them can draw it.
        $results = [];
        foreach ($people as [$phone, $id]) {
            $this->usePhone($phone);
            $results[] = $this->ping($id, $this->fix())->json('data');
        }

        $this->assertSame('rewarded', $results[0]['status']);
        $this->assertSame('rejected', $results[1]['status']);
        $this->assertSame('campaign_exhausted', $results[1]['rejection_reason']);
        $this->assertSame(50, $campaign->fresh()->points_spent);
        $this->assertSame(50, $sponsor->fresh()->points_spent);
    }

    public function test_sponsor_budget_caps_all_campaigns_together(): void
    {
        $sponsor = $this->sponsor(budget: 50);
        $location = $this->location($sponsor);
        $a = $this->campaign($sponsor, $location, ['min_stay_seconds' => 30]);
        $b = $this->campaign($sponsor, $location, ['name' => 'دوم', 'min_stay_seconds' => 30]);

        $this->loginAs();
        $id = $this->start($a, $location, $this->fix())->json('data.id');
        $this->ping($id, $this->fix())->assertJsonPath('data.status', 'rewarded');
        $this->start($b, $location, $this->fix())->assertStatus(409)->assertJsonPath('error.code', 'campaign_exhausted');
        $this->assertSame(50, $sponsor->fresh()->points_spent);
    }

    public function test_offers_must_be_approved_and_open(): void
    {
        $this->loginAs();
        $pending = $this->sponsor(status: SponsorStatus::Pending);
        $l1 = $this->location($pending);
        $c1 = $this->campaign($pending, $l1);
        $this->start($c1, $l1, $this->fix())->assertStatus(422)->assertJsonPath('error.code', 'campaign_unavailable');

        $sponsor = $this->sponsor();
        $unapproved = $this->location($sponsor, ['status' => LocationStatus::Pending]);
        $c2 = $this->campaign($sponsor, $unapproved);
        $this->start($c2, $unapproved, $this->fix())->assertStatus(422)->assertJsonPath('error.code', 'campaign_unavailable');

        // 12:00 Tehran on a Thursday; open only in the evening.
        $closed = $this->location($sponsor, ['opening_hours' => ['thu' => [['17:00', '23:00']]]]);
        $c3 = $this->campaign($sponsor, $closed);
        $this->start($c3, $closed, $this->fix())->assertStatus(422)->assertJsonPath('error.code', 'location_closed');
    }

    public function test_cooldown_between_rewards_at_the_same_branch(): void
    {
        $this->loginAs();
        $sponsor = $this->sponsor();
        $location = $this->location($sponsor);
        $campaign = $this->campaign($sponsor, $location, ['max_rewards_per_user' => 5, 'cooldown_hours' => 20, 'min_stay_seconds' => 30]);

        $id = $this->start($campaign, $location, $this->fix())->json('data.id');
        $this->ping($id, $this->fix())->assertJsonPath('data.status', 'rewarded');
        $this->start($campaign, $location, $this->fix())->assertStatus(409)->assertJsonPath('error.code', 'cooldown');

        $this->travel(21)->hours();
        $this->start($campaign, $location, $this->fix())->assertCreated();
    }

    public function test_nearby_lists_running_offers_with_eligibility(): void
    {
        $this->loginAs();
        $sponsor = $this->sponsor();
        $near = $this->location($sponsor);
        $this->campaign($sponsor, $near);
        $far = $this->location($sponsor, ['name' => 'کرج', 'latitude' => 35.84, 'longitude' => 50.93]);
        $this->campaign($sponsor, $far);
        $noCampaign = $this->location($sponsor, ['name' => 'بدون کمپین', 'latitude' => self::LAT + 0.002]);
        $pending = $this->location($this->sponsor(status: SponsorStatus::Pending), ['name' => 'تأییدنشده']);
        $this->campaign($pending->sponsor, $pending);

        $res = $this->authedJson('GET', '/api/v1/locations/nearby?lat='.(self::LAT + 0.003).'&lng='.self::LNG.'&radius_km=5')->assertOk();

        $this->assertSame(['شعبه آزادی'], array_column($res->json('data'), 'name'));
        $res->assertJsonPath('data.0.distance_m', 330)
            ->assertJsonPath('data.0.campaigns.0.eligible', true)
            ->assertJsonPath('data.0.campaigns.0.reward_points', 50)
            ->assertJsonMissingPath('data.0.qr_secret');
        $this->assertNotContains($noCampaign->name, array_column($res->json('data'), 'name'));
    }

    public function test_campaign_detail_and_visit_history(): void
    {
        $this->loginAs();
        $sponsor = $this->sponsor();
        $location = $this->location($sponsor);
        $campaign = $this->campaign($sponsor, $location, ['description' => 'یک فنجان قهوه مهمان ما']);

        $this->authedJson('GET', "/api/v1/campaigns/{$campaign->public_id}")->assertOk()
            ->assertJsonPath('data.sponsor.name', 'کافه نمونه')
            ->assertJsonPath('data.locations.0.id', $location->public_id)
            ->assertJsonPath('data.eligible', true);

        $this->start($campaign, $location, $this->fix());
        $this->authedJson('GET', '/api/v1/visits')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_housekeeping_expires_abandoned_visits_and_erases_their_position(): void
    {
        $this->loginAs();
        $sponsor = $this->sponsor();
        $location = $this->location($sponsor);
        $campaign = $this->campaign($sponsor, $location);
        $id = $this->start($campaign, $location, $this->fix())->json('data.id');

        $this->travel(30)->minutes();
        $this->artisan('sponsors:housekeeping')->assertSuccessful();

        $visit = Visit::query()->where('public_id', $id)->sole();
        $this->assertSame(VisitStatus::Expired, $visit->status);
        $this->assertNull($visit->last_lat);
    }

    public function test_visits_of_other_users_are_invisible(): void
    {
        $this->loginAs('09121111111');
        $sponsor = $this->sponsor();
        $location = $this->location($sponsor);
        $campaign = $this->campaign($sponsor, $location);
        $id = $this->start($campaign, $location, $this->fix())->json('data.id');

        $this->asNewPhone('09122222222');
        $this->signedJson('POST', "/api/v1/visits/{$id}/ping", $this->fix())->assertNotFound();
        $this->authedJson('GET', "/api/v1/visits/{$id}")->assertNotFound();
        $this->assertSame(0, UserCoupon::query()->count());
    }
}
