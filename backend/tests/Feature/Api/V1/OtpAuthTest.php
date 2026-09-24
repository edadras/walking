<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserStatus;
use App\Jobs\SendOtpSms;
use App\Models\OtpCode;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class OtpAuthTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([SendOtpSms::class]);
        $this->registerDevice()->assertCreated();
    }

    private function requestCode(string $phone = '09121234567'): string
    {
        $this->signedJson('POST', '/api/v1/auth/otp/request', ['phone' => $phone])
            ->assertOk()
            ->assertJsonStructure(['data' => ['expires_in', 'resend_in']]);

        $code = null;
        Bus::assertDispatched(SendOtpSms::class, function (SendOtpSms $job) use (&$code) {
            $code = $job->code;

            return true;
        });

        return $code;
    }

    public function test_full_login_creates_user_profile_and_device_bound_token(): void
    {
        $code = $this->requestCode();

        $response = $this->signedJson('POST', '/api/v1/auth/otp/verify', ['phone' => '0912 123 4567', 'code' => $code, 'timezone' => 'Asia/Tehran'])
            ->assertOk()
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.user.settings.daily_step_goal', 7500)
            ->assertJsonMissingPath('data.user.phone');

        $user = User::query()->firstOrFail();
        $this->assertSame('+989121234567', $user->phone);
        $this->assertNotNull($user->profile);
        $this->assertTrue($user->devices()->whereKey($this->device->id)->exists());

        $token = PersonalAccessToken::query()->firstOrFail();
        $this->assertSame($this->device->id, $token->device_id);
        $this->assertNotNull($token->expires_at);

        $this->token = $response->json('data.token');
        $this->authedJson('GET', '/api/v1/me')->assertOk()->assertJsonPath('data.phone_masked', '0912***4567');
    }

    public function test_codes_are_stored_hashed(): void
    {
        $code = $this->requestCode();

        $this->assertNotSame($code, OtpCode::query()->value('code_hash'));
        $this->assertStringNotContainsString($code, (string) OtpCode::query()->value('code_hash'));
    }

    public function test_persian_digits_are_accepted(): void
    {
        $code = $this->requestCode('۰۹۱۲۱۲۳۴۵۶۷');
        $persian = strtr($code, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);

        $this->signedJson('POST', '/api/v1/auth/otp/verify', ['phone' => '09121234567', 'code' => $persian])->assertOk();
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $this->signedJson('POST', '/api/v1/auth/otp/request', ['phone' => '0212345678'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.fields.phone.0', 'شماره موبایل معتبر نیست.');
    }

    public function test_wrong_code_counts_attempts_and_locks_after_limit(): void
    {
        $code = $this->requestCode();
        $wrong = $code === '00000' ? '11111' : '00000';

        $this->signedJson('POST', '/api/v1/auth/otp/verify', ['phone' => '09121234567', 'code' => $wrong])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'otp_invalid')
            ->assertJsonPath('error.context.remaining_attempts', 4);

        for ($i = 0; $i < 4; $i++) {
            $this->signedJson('POST', '/api/v1/auth/otp/verify', ['phone' => '09121234567', 'code' => $wrong]);
        }

        // Even the right code no longer works once attempts are exhausted.
        $this->signedJson('POST', '/api/v1/auth/otp/verify', ['phone' => '09121234567', 'code' => $code])
            ->assertStatus(422)->assertJsonPath('error.code', 'otp_expired');
        $this->assertSame(0, User::query()->count());
    }

    public function test_expired_code_is_rejected(): void
    {
        $code = $this->requestCode();
        $this->travel(3)->minutes();

        $this->signedJson('POST', '/api/v1/auth/otp/verify', ['phone' => '09121234567', 'code' => $code])
            ->assertStatus(422)->assertJsonPath('error.code', 'otp_expired');
    }

    public function test_code_cannot_be_used_twice(): void
    {
        $code = $this->requestCode();
        $this->signedJson('POST', '/api/v1/auth/otp/verify', ['phone' => '09121234567', 'code' => $code])->assertOk();

        $this->signedJson('POST', '/api/v1/auth/otp/verify', ['phone' => '09121234567', 'code' => $code])
            ->assertStatus(422)->assertJsonPath('error.code', 'otp_expired');
    }

    public function test_resend_is_throttled(): void
    {
        $this->requestCode();

        $this->signedJson('POST', '/api/v1/auth/otp/request', ['phone' => '09121234567'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'too_many_requests');
    }

    public function test_requesting_a_new_code_invalidates_the_old_one(): void
    {
        $old = $this->requestCode();
        $this->travel(61)->seconds();
        Bus::fake([SendOtpSms::class]);
        $new = $this->requestCode();

        if ($old !== $new) {
            $this->signedJson('POST', '/api/v1/auth/otp/verify', ['phone' => '09121234567', 'code' => $old])
                ->assertStatus(422);
        }
        $this->signedJson('POST', '/api/v1/auth/otp/verify', ['phone' => '09121234567', 'code' => $new])->assertOk();
    }

    public function test_banned_user_cannot_log_in(): void
    {
        User::factory()->status(UserStatus::Banned)->create(['phone' => '+989121234567']);
        $code = $this->requestCode();

        $this->signedJson('POST', '/api/v1/auth/otp/verify', ['phone' => '09121234567', 'code' => $code])
            ->assertForbidden()->assertJsonPath('error.code', 'account_banned');
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_referral_code_links_new_user_to_referrer(): void
    {
        $referrer = User::factory()->create(['referral_code' => 'ABCD234']);
        $code = $this->requestCode();

        $this->signedJson('POST', '/api/v1/auth/otp/verify', ['phone' => '09121234567', 'code' => $code, 'referral_code' => 'abcd234'])->assertOk();

        $this->assertSame($referrer->id, User::query()->where('phone', '+989121234567')->value('referred_by_id'));
    }

    public function test_new_login_on_same_device_revokes_previous_token(): void
    {
        $this->loginAs('09121234567');
        $first = $this->token;

        $this->travel(2)->minutes();
        $this->loginAs('09350000000');

        $this->assertSame(1, PersonalAccessToken::query()->count());
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$first])->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_logout_revokes_the_token(): void
    {
        $this->loginAs();

        $this->authedJson('POST', '/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->authedJson('GET', '/api/v1/me')->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_token_cannot_be_used_with_another_devices_signature(): void
    {
        $this->loginAs();
        $stolenToken = $this->token;

        // Attacker registers their own device and replays the stolen token on a signed route.
        $this->deviceKey = $this->newDeviceKey();
        $this->device = null;
        $this->registerDevice()->assertCreated();
        $this->token = $stolenToken;

        $this->signedJson('POST', '/api/v1/me/deletion-request')
            ->assertUnauthorized()->assertJsonPath('error.code', 'device_mismatch');
    }

    public function test_blocked_device_loses_access_immediately(): void
    {
        $this->loginAs();
        $this->device->forceFill(['status' => 'blocked'])->save();

        $this->authedJson('GET', '/api/v1/me')->assertForbidden()->assertJsonPath('error.code', 'device_blocked');
    }

    public function test_suspended_user_gets_stable_error_code(): void
    {
        $user = $this->loginAs();
        $user->forceFill(['status' => UserStatus::Suspended])->save();

        $this->authedJson('GET', '/api/v1/me')->assertForbidden()->assertJsonPath('error.code', 'account_suspended');
    }
}
