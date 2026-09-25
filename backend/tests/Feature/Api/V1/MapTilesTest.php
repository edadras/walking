<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Settings\Settings;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class MapTilesTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        Cache::store('file')->flush();
    }

    public function test_config_points_at_the_admin_url_or_the_proxy(): void
    {
        $this->getJson('/api/v1/config')->assertJsonPath('data.map.tile_url', null)->assertJsonPath('data.map.attribution', 'OpenStreetMap');

        app(Settings::class)->set('map.tile_url', 'https://tiles.example.ir/{z}/{x}/{y}.png');
        $this->getJson('/api/v1/config')->assertJsonPath('data.map.tile_url', 'https://tiles.example.ir/{z}/{x}/{y}.png')->assertJsonPath('data.map.proxied', false);

        config(['walk.map.upstream' => 'https://provider.test/tiles/{z}/{x}/{y}.png']);
        $this->getJson('/api/v1/config')->assertJsonPath('data.map.proxied', true)
            ->assertJsonPath('data.map.tile_url', url('/api/v1/map/tiles').'/{z}/{x}/{y}');
    }

    public function test_proxy_adds_the_secret_header_caches_and_validates(): void
    {
        config(['walk.map.upstream' => 'https://provider.test/tiles/{z}/{x}/{y}.png', 'walk.map.upstream_headers' => 'x-api-key: secret']);
        Http::fake(['provider.test/*' => Http::response("\x89PNG-tile", 200)]);

        $this->getJson('/api/v1/map/tiles/14/10523/6450')->assertUnauthorized();

        $this->loginAs();
        $headers = ['Authorization' => 'Bearer '.$this->token];
        $this->get('/api/v1/map/tiles/14/10523/6450', $headers)->assertOk()
            ->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'max-age=1209600, public');
        $this->get('/api/v1/map/tiles/14/10523/6450', $headers)->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://provider.test/tiles/14/10523/6450.png' && $r->header('x-api-key') === ['secret']);

        $this->get('/api/v1/map/tiles/3/9/0', $headers)->assertNotFound();
        $this->get('/api/v1/map/tiles/25/0/0', $headers)->assertNotFound();
    }

    public function test_upstream_failures_are_not_cached(): void
    {
        config(['walk.map.upstream' => 'https://provider.test/{z}/{x}/{y}']);
        Http::fakeSequence('provider.test/*')->push('down', 503)->push("\x89PNG", 200);
        $this->loginAs();
        $headers = ['Authorization' => 'Bearer '.$this->token];

        $this->get('/api/v1/map/tiles/1/0/0', $headers)->assertStatus(502);
        $this->get('/api/v1/map/tiles/1/0/0', $headers)->assertOk();
    }

    public function test_without_an_upstream_the_proxy_is_off(): void
    {
        $this->loginAs();
        $this->get('/api/v1/map/tiles/1/0/0', ['Authorization' => 'Bearer '.$this->token])->assertNotFound();
    }
}
