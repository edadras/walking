<?php

namespace Tests\Feature\Weather;

use App\Domain\Weather\WalkAdvice;
use App\Domain\Weather\WeatherProvider;
use App\Domain\Weather\WeatherService;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class WeatherTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        Cache::flush();
    }

    private bool $down = false;

    /** Air first: the forecast pattern would also match the air-quality host. */
    private function fakeUpstream(): void
    {
        Http::fake(function (Request $r) {
            if ($this->down) {
                return Http::response('down', 503);
            }
            $file = str_contains($r->url(), 'air-quality') ? 'air' : 'forecast';

            return Http::response(file_get_contents(base_path("tests/Fixtures/weather/{$file}.json")));
        });
    }

    public function test_report_has_current_forecast_air_and_advice(): void
    {
        $this->fakeUpstream();
        $this->loginAs();

        $r = $this->authedJson('GET', '/api/v1/weather?lat=35.7219&lng=51.3347')->assertOk()->json('data');

        $this->assertSame(['lat' => 35.7, 'lng' => 51.35, 'elevation_m' => 1199], $r['location']);
        $this->assertFalse($r['stale']);
        $this->assertSame(33.2, $r['current']['temp_c']);
        $this->assertSame(29.5, $r['current']['feels_like_c']);
        $this->assertSame(10, $r['current']['humidity']);
        $this->assertSame('جنوب', $r['current']['wind_dir']);
        $this->assertSame('صاف', $r['current']['condition']);
        $this->assertSame('clear', $r['current']['icon']);
        $this->assertSame('low', $r['current']['uv_level']);
        $this->assertSame(['us_aqi' => 93, 'pm2_5' => 10.4, 'pm10' => 17.5, 'level' => 'moderate', 'label' => 'قابل قبول'], $r['air']);
        $this->assertCount(12, $r['hourly']);
        $this->assertSame('2026-09-25T16:00:00+03:30', $r['hourly'][0]['time']);
        $this->assertSame('clear_night', $r['hourly'][2]['icon']);
        $this->assertCount(3, $r['daily']);
        $this->assertSame('2026-09-25T17:57:00+03:30', $r['sun']['sunset']);
        $this->assertSame('dry', $r['advice'][0]['key']);
        $this->assertSame(['score' => 100, 'label' => 'عالی'], $r['walk_index']);

        // Coordinates never leave the server unsnapped.
        Http::assertSent(fn (Request $req) => str_contains($req->url(), 'latitude=35.7&longitude=51.35'));
    }

    public function test_one_upstream_call_serves_a_grid_cell_and_outage_serves_stale(): void
    {
        $this->fakeUpstream();
        $this->loginAs();
        $this->authedJson('GET', '/api/v1/weather?lat=35.70&lng=51.40')->assertOk();
        $this->authedJson('GET', '/api/v1/weather?lat=35.71&lng=51.39')->assertOk();
        Http::assertSentCount(2); // forecast + air, once

        // Fresh copy expires, provider goes down → last good report, flagged stale.
        Cache::forget('weather:v1:35.70:51.40');
        $this->down = true;
        $this->authedJson('GET', '/api/v1/weather?lat=35.70&lng=51.40')->assertOk()->assertJsonPath('data.stale', true);

        // Nothing cached anywhere → honest 503.
        $this->authedJson('GET', '/api/v1/weather?lat=29.60&lng=52.50')->assertStatus(503);
    }

    public function test_air_quality_failure_keeps_the_forecast(): void
    {
        Http::fake([
            'air-quality-api.open-meteo.com/*' => Http::response('x', 500),
            'api.open-meteo.com/*' => Http::response(file_get_contents(base_path('tests/Fixtures/weather/forecast.json'))),
        ]);
        $this->loginAs();
        $this->authedJson('GET', '/api/v1/weather?lat=35.7&lng=51.4')->assertOk()->assertJsonPath('data.air', null);
    }

    public function test_requires_sign_in_and_valid_coordinates(): void
    {
        $this->getJson('/api/v1/weather?lat=35&lng=51')->assertUnauthorized();
        $this->loginAs();
        $this->authedJson('GET', '/api/v1/weather?lat=95&lng=51')->assertUnprocessable();
    }

    public function test_commercial_key_switches_to_customer_hosts(): void
    {
        config(['walk.weather.api_key' => 'k1']);
        $this->app->forgetInstance(WeatherProvider::class);
        $this->fakeUpstream();
        $this->loginAs();
        $this->authedJson('GET', '/api/v1/weather?lat=35.7&lng=51.4')->assertOk();
        Http::assertSent(fn (Request $req) => str_starts_with($req->url(), 'https://customer-api.open-meteo.com/') && str_contains($req->url(), 'apikey=k1'));
    }

    public function test_advice_flags_heat_humidity_uv_air_and_storms(): void
    {
        $report = fn (array $current, ?int $aqi = null) => [
            'current' => $current + ['temp_c' => 30, 'feels_like_c' => 30, 'humidity' => 40, 'wind_kmh' => 5, 'wind_gusts_kmh' => 10, 'uv' => 2, 'is_day' => true, 'icon' => 'clear'],
            'air' => $aqi === null ? null : ['us_aqi' => $aqi],
            'hourly' => [['precip_prob' => 0]],
            'sun' => ['sunset' => '2026-07-01T20:00:00+03:30'],
        ];
        $now = CarbonImmutable::parse('2026-07-01T12:00:00+03:30');
        $keys = fn (array $r) => array_column(WalkAdvice::for($r, $now)['items'], 'key');

        $muggy = WalkAdvice::for($report(['temp_c' => 34, 'feels_like_c' => 42, 'humidity' => 75, 'uv' => 9]), $now);
        $this->assertSame(['heat', 'uv', 'muggy'], array_column($muggy['items'], 'key'));
        $this->assertSame('danger', $muggy['items'][0]['level']);
        $this->assertLessThan(40, $muggy['index']['score']);

        $this->assertSame(['air'], $keys($report([], 180)));
        $this->assertSame(['storm'], $keys($report(['icon' => 'thunder'])));
        $this->assertSame(['great'], $keys($report([], 40)));
        $this->assertSame(['sunset'], $keys(['sun' => ['sunset' => '2026-07-01T12:30:00+03:30']] + $report([])));
    }

    public function test_grid_and_compass(): void
    {
        $this->assertSame(35.7, WeatherService::snap(35.7219));
        $this->assertSame(51.35, WeatherService::snap(51.3347));
        $this->assertSame('شمال', WeatherService::compass(355));
        $this->assertSame('شمال‌غرب', WeatherService::compass(310));
    }
}
