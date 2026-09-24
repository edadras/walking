<?php

namespace Tests\Feature\Api\V1;

use App\Models\AccountDeletionRequest;
use App\Models\PersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    public function test_me_never_exposes_raw_phone_or_internal_id(): void
    {
        $this->loginAs();

        $data = $this->authedJson('GET', '/api/v1/me')->assertOk()->json('data');

        $this->assertArrayNotHasKey('phone', $data);
        $this->assertIsString($data['id']);
        $this->assertSame(26, strlen($data['id']));
    }

    public function test_updates_profile(): void
    {
        $this->loginAs();

        $this->authedJson('PATCH', '/api/v1/me', [
            'display_name' => 'سارا',
            'birth_year' => 1995,
            'height_cm' => 168,
            'weight_kg' => 61.5,
        ])->assertOk()
            ->assertJsonPath('data.display_name', 'سارا')
            ->assertJsonPath('data.profile.weight_kg', 61.5)
            ->assertJsonPath('data.profile_completed', true);
    }

    public function test_rejects_out_of_range_values(): void
    {
        $this->loginAs();

        $this->authedJson('PATCH', '/api/v1/me', ['height_cm' => 20])
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->authedJson('PATCH', '/api/v1/me/settings', ['daily_step_goal' => 500])
            ->assertStatus(422);
        $this->authedJson('PATCH', '/api/v1/me/settings', ['daily_step_goal' => 12000, 'leaderboard_visible' => false])
            ->assertOk()
            ->assertJsonPath('data.settings.daily_step_goal', 12000)
            ->assertJsonPath('data.settings.leaderboard_visible', false);
    }

    public function test_timezone_change_has_a_cooldown(): void
    {
        $this->loginAs();

        $this->authedJson('PATCH', '/api/v1/me', ['timezone' => 'Asia/Dubai'])->assertOk()->assertJsonPath('data.timezone', 'Asia/Dubai');
        $this->authedJson('PATCH', '/api/v1/me', ['timezone' => 'Europe/Berlin'])
            ->assertStatus(422)->assertJsonPath('error.code', 'timezone_change_too_soon');

        $this->travel(8)->days();
        $this->authedJson('PATCH', '/api/v1/me', ['timezone' => 'Asia/Tehran'])->assertOk();
    }

    public function test_notification_preferences_respect_mandatory_categories(): void
    {
        $this->loginAs();

        $this->authedJson('GET', '/api/v1/me/notification-preferences')->assertOk()->assertJsonPath('data.water_reminder', true);

        $this->authedJson('PATCH', '/api/v1/me/notification-preferences', ['preferences' => ['water_reminder' => false, 'order_update' => false]])
            ->assertOk()
            ->assertJsonPath('data.water_reminder', false)
            ->assertJsonPath('data.order_update', true);

        $this->authedJson('PATCH', '/api/v1/me/notification-preferences', ['preferences' => ['nope' => false]])->assertStatus(422);
    }

    public function test_avatar_upload(): void
    {
        Storage::fake('public');
        $this->loginAs();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->post('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('a.png', 256, 256)], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertNotNull($response->json('data.avatar_url'));
    }

    public function test_deletion_request_requires_signature_and_can_be_cancelled(): void
    {
        $this->loginAs();

        $this->authedJson('POST', '/api/v1/me/deletion-request')->assertStatus(401)->assertJsonPath('error.code', 'device_not_registered');

        $this->signedJson('POST', '/api/v1/me/deletion-request')->assertStatus(202)->assertJsonPath('data.status', 'pending');
        $this->assertSame(1, AccountDeletionRequest::query()->where('status', 'pending')->count());

        $this->authedJson('DELETE', '/api/v1/me/deletion-request')->assertOk();
        $this->assertSame(0, AccountDeletionRequest::query()->where('status', 'pending')->count());
    }

    public function test_lists_and_revokes_other_devices(): void
    {
        $this->loginAs();
        $firstDevice = $this->device;
        $firstKey = $this->deviceKey;
        $firstToken = $this->token;

        // Log in on a second phone.
        $this->device = null;
        $this->deviceKey = $this->newDeviceKey();
        $this->travel(2)->minutes();
        $this->loginAs();

        $devices = $this->authedJson('GET', '/api/v1/auth/devices')->assertOk()->json('data');
        $this->assertCount(2, $devices);
        $this->assertCount(1, array_filter($devices, fn ($d) => $d['is_current']));

        $this->signedJson('DELETE', '/api/v1/auth/devices/'.$firstDevice->public_id)->assertNoContent();
        $this->assertFalse(PersonalAccessToken::query()->where('device_id', $firstDevice->id)->exists());

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$firstToken])->getJson('/api/v1/me')->assertUnauthorized();
        unset($firstKey);
    }
}
