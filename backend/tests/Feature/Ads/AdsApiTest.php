<?php

namespace Tests\Feature\Ads;

use App\Domain\Settings\FeatureFlags;
use App\Enums\AdFormat;
use App\Enums\AdViewStatus;
use App\Enums\CampaignStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Ad;
use App\Models\AdCampaign;
use App\Models\AdPlacement;
use App\Models\AdProvider;
use App\Models\AdView;
use App\Models\FeatureFlag;
use App\Models\PointTransaction;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class AdsApiTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 12:00:00', 'Asia/Tehran')->utc());
        $this->seed(PlatformSeeder::class);
        $this->flag('ads', true);
        $this->flag('rewarded_ads', true);
    }

    private function flag(string $key, bool $on): void
    {
        FeatureFlag::query()->updateOrCreate(['key' => $key], ['is_enabled' => $on, 'rollout_percent' => 100]);
        app(FeatureFlags::class)->flush();
    }

    private function campaign(string $placement, AdFormat $format, array $overrides = []): AdCampaign
    {
        $c = AdCampaign::query()->create([
            'name' => 'کمپین '.$placement, 'status' => CampaignStatus::Active, 'starts_at' => now()->subDay(), 'ends_at' => now()->addWeek(), ...$overrides,
        ]);
        $c->placements()->attach(AdPlacement::query()->where('key', $placement)->value('id'));
        Ad::query()->create(['ad_campaign_id' => $c->id, 'format' => $format, 'title' => 'کفش پیاده‌روی', 'body' => 'راحت برای هر روز', 'cta_label' => 'مشاهده', 'action_url' => 'https://example.com/shoes', 'min_view_seconds' => 15]);

        return $c;
    }

    public function test_serving_and_events_are_tokenised_idempotent_and_capped(): void
    {
        $this->loginAs();
        $campaign = $this->campaign('home_banner', AdFormat::Banner, ['frequency_cap_per_day' => 2]);

        $first = $this->authedJson('GET', '/api/v1/ads/placements/home_banner')->assertOk()->assertJsonPath('data.title', 'کفش پیاده‌روی');
        $token = $first->json('data.token');

        // A click before the impression doesn't count; duplicates are ignored; forged tokens rejected.
        $this->authedJson('POST', '/api/v1/ads/events', ['events' => [['token' => $token, 'type' => 'click']]])->assertJsonPath('data.accepted', 0);
        $this->authedJson('POST', '/api/v1/ads/events', ['events' => [
            ['token' => $token, 'type' => 'impression'], ['token' => $token, 'type' => 'impression'],
            ['token' => $token, 'type' => 'click'], ['token' => $token.'x', 'type' => 'impression'],
        ]])->assertJsonPath('data.accepted', 2)->assertJsonPath('data.rejected', 2);

        $this->assertSame(1, $campaign->fresh()->impressions_count);
        $this->assertSame(1, $campaign->fresh()->clicks_count);

        $second = $this->authedJson('GET', '/api/v1/ads/placements/home_banner')->json('data.token');
        $this->authedJson('POST', '/api/v1/ads/events', ['events' => [['token' => $second, 'type' => 'impression']]]);
        // Frequency cap (2/day) reached for this user.
        $this->authedJson('GET', '/api/v1/ads/placements/home_banner')->assertOk()->assertJsonPath('data', null);
    }

    public function test_tokens_of_another_user_are_worthless(): void
    {
        $this->loginAs('09121111111');
        $this->campaign('home_banner', AdFormat::Banner);
        $token = $this->authedJson('GET', '/api/v1/ads/placements/home_banner')->json('data.token');

        $this->device = null;
        $this->deviceKey = null;
        $this->loginAs('09122222222');
        $this->authedJson('POST', '/api/v1/ads/events', ['events' => [['token' => $token, 'type' => 'impression']]])->assertJsonPath('data.accepted', 0);
    }

    public function test_flags_targeting_and_limits_gate_serving(): void
    {
        $user = $this->loginAs();
        $this->campaign('home_banner', AdFormat::Banner, ['targeting' => ['min_level' => 5]]);
        $this->authedJson('GET', '/api/v1/ads/placements/home_banner')->assertJsonPath('data', null);

        $user->forceFill(['level' => 6])->save();
        $this->app['auth']->forgetGuards();
        $this->authedJson('GET', '/api/v1/ads/placements/home_banner')->assertJsonPath('data.format', 'banner');

        $this->flag('ads', false);
        $this->authedJson('GET', '/api/v1/ads/placements/home_banner')->assertJsonPath('data', null);
        $this->flag('ads', true);

        AdCampaign::query()->update(['impression_limit' => 0]);
        $this->authedJson('GET', '/api/v1/ads/placements/home_banner')->assertJsonPath('data', null);
        $this->authedJson('GET', '/api/v1/ads/placements/unknown')->assertOk()->assertJsonPath('data', null);
    }

    public function test_internal_rewarded_view_pays_only_after_server_timed_minimum(): void
    {
        $user = $this->loginAs();
        $campaign = $this->campaign('rewarded_default', AdFormat::Rewarded, ['reward_points' => 15, 'point_budget' => 1000]);

        $this->authedJson('GET', '/api/v1/ads/rewarded')->assertJsonPath('data.enabled', true)->assertJsonPath('data.remaining_today', 3);
        $id = $this->signedJson('POST', '/api/v1/ads/rewarded/start', ['placement' => 'rewarded_default'])->assertCreated()
            ->assertJsonPath('data.reward_points', 15)->json('data.id');

        $this->travel(5)->seconds();
        $this->signedJson('POST', "/api/v1/ads/rewarded/{$id}/complete")->assertStatus(422)->assertJsonPath('error.code', 'too_early');

        $this->travel(11)->seconds();
        $this->signedJson('POST', "/api/v1/ads/rewarded/{$id}/complete")->assertOk()->assertJsonPath('data.status', 'rewarded')->assertJsonPath('data.points_awarded', 15);
        $this->signedJson('POST', "/api/v1/ads/rewarded/{$id}/complete")->assertOk()->assertJsonPath('data.points_awarded', 15);

        $tx = PointTransaction::query()->where('user_id', $user->id)->sole();
        $this->assertSame(TransactionType::AdReward, $tx->type);
        $this->assertSame(TransactionStatus::Pending, $tx->status);
        $this->assertSame(15, $campaign->fresh()->points_spent);
    }

    public function test_rewarded_daily_cap_and_budget(): void
    {
        $user = $this->loginAs();
        $this->campaign('rewarded_default', AdFormat::Rewarded, ['reward_points' => 10, 'point_budget' => 1000]);

        foreach (range(1, 3) as $_) {
            $id = $this->signedJson('POST', '/api/v1/ads/rewarded/start', ['placement' => 'rewarded_default'])->json('data.id');
            $this->travel(16)->seconds();
            $this->signedJson('POST', "/api/v1/ads/rewarded/{$id}/complete")->assertJsonPath('data.status', 'rewarded');
        }
        $this->signedJson('POST', '/api/v1/ads/rewarded/start', ['placement' => 'rewarded_default'])->assertStatus(409)->assertJsonPath('error.code', 'daily_cap');
        $this->assertSame(30, (int) PointTransaction::query()->where('user_id', $user->id)->sum('amount'));

        // Next day, but the campaign budget only covers part of a view.
        $this->travel(1)->days();
        AdCampaign::query()->update(['point_budget' => 35]);
        $this->signedJson('POST', '/api/v1/ads/rewarded/start', ['placement' => 'rewarded_default'])->assertStatus(409)->assertJsonPath('error.code', 'no_fill');
    }

    public function test_starting_again_expires_the_open_view_and_stale_views_expire(): void
    {
        $this->loginAs();
        $this->campaign('rewarded_default', AdFormat::Rewarded, ['reward_points' => 10, 'point_budget' => 1000]);

        $a = $this->signedJson('POST', '/api/v1/ads/rewarded/start', ['placement' => 'rewarded_default'])->json('data.id');
        $b = $this->signedJson('POST', '/api/v1/ads/rewarded/start', ['placement' => 'rewarded_default'])->json('data.id');
        $this->assertSame(AdViewStatus::Expired, AdView::query()->where('public_id', $a)->sole()->status);

        $this->travel(11)->minutes();
        $this->signedJson('POST', "/api/v1/ads/rewarded/{$b}/complete")->assertJsonPath('data.status', 'expired');
    }

    public function test_external_views_are_rewarded_only_by_a_signed_callback(): void
    {
        $this->loginAs();
        $provider = AdProvider::query()->where('key', 'yektanet')->sole();
        AdPlacement::query()->where('key', 'rewarded_default')->update(['ad_provider_id' => $provider->id]);
        $this->campaign('rewarded_default', AdFormat::Rewarded, ['reward_points' => 10, 'point_budget' => 1000]);

        $id = $this->signedJson('POST', '/api/v1/ads/rewarded/start', ['placement' => 'rewarded_default'])->json('data.id');
        $this->travel(30)->seconds();
        // The app can't self-report an external view.
        $this->signedJson('POST', "/api/v1/ads/rewarded/{$id}/complete")->assertStatus(422)->assertJsonPath('error.code', 's2s_required');

        $body = json_encode(['view_id' => $id, 'transaction_id' => 'yk-1']);
        $post = fn (string $sig) => $this->call('POST', '/api/v1/webhooks/ads/yektanet', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $sig], $body);

        // Disabled provider (docs not verified): everything refused.
        $post('whatever')->assertForbidden();

        $provider->forceFill(['is_enabled' => true, 'webhook_secret' => 'shared-secret'])->save();
        $post(hash_hmac('sha256', $body, 'wrong'))->assertForbidden();
        $post(hash_hmac('sha256', $body, 'shared-secret'))->assertOk()->assertJsonPath('data.status', 'rewarded');
        $post(hash_hmac('sha256', $body, 'shared-secret'))->assertOk();

        $this->assertSame(1, PointTransaction::query()->where('type', TransactionType::AdReward)->count());
    }
}
