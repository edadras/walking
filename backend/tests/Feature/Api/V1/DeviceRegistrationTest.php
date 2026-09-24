<?php

namespace Tests\Feature\Api\V1;

use App\Models\AuditLog;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    public function test_registers_a_device_with_a_self_signed_request(): void
    {
        $response = $this->registerDevice()->assertCreated();

        $device = Device::query()->where('public_id', $response->json('data.device_id'))->firstOrFail();
        $this->assertSame('android', $device->platform);
        $this->assertSame('unavailable', $device->integrity_verdict->value);
        $this->assertTrue(AuditLog::query()->where('action', 'device.registered')->exists());
    }

    public function test_re_registering_the_same_install_and_key_is_idempotent(): void
    {
        $installId = (string) Str::uuid();
        $first = $this->registerDevice(['install_id' => $installId])->assertCreated();
        $second = $this->registerDevice(['install_id' => $installId])->assertOk();

        $this->assertSame($first->json('data.device_id'), $second->json('data.device_id'));
        $this->assertSame(1, Device::query()->count());
    }

    public function test_same_install_with_a_different_key_is_rejected(): void
    {
        $installId = (string) Str::uuid();
        $this->registerDevice(['install_id' => $installId])->assertCreated();

        $this->deviceKey = $this->newDeviceKey();
        $this->registerDevice(['install_id' => $installId])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'device_key_conflict');
    }

    public function test_request_signed_by_another_key_is_rejected(): void
    {
        $this->deviceKey = $this->newDeviceKey();
        $otherKey = $this->newDeviceKey();

        // Submit key A but sign with key B: proof of possession fails.
        $payload = ['install_id' => (string) Str::uuid(), 'platform' => 'android', 'public_key' => $this->publicPem($this->deviceKey)];
        $body = json_encode($payload);
        $this->call('POST', '/api/v1/devices/register', [], [], [], $this->transformHeadersToServerVars(
            $this->signatureHeaders($otherKey, 'POST', '/api/v1/devices/register', $body) + ['Content-Type' => 'application/json', 'Accept' => 'application/json']
        ), $body)->assertStatus(400)->assertJsonPath('error.code', 'signature_invalid');

        $this->assertSame(0, Device::query()->count());
    }

    public function test_only_p256_keys_are_accepted(): void
    {
        $rsa = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $this->deviceKey = $rsa;

        $this->registerDevice()->assertStatus(422)->assertJsonPath('error.code', 'public_key_invalid');
    }

    public function test_accepts_a_base64_der_public_key(): void
    {
        $this->deviceKey = $this->newDeviceKey();
        $pem = $this->publicPem($this->deviceKey);
        $der = base64_encode(base64_decode(preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem)));

        $this->registerDevice(['public_key' => $der])->assertCreated();
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $this->registerDevice()->assertCreated();

        $body = json_encode(['phone' => '09121234567']);
        $headers = $this->signatureHeaders($this->deviceKey, 'POST', '/api/v1/auth/otp/request', $body, time() - 3600);

        $this->call('POST', '/api/v1/auth/otp/request', [], [], [], $this->transformHeadersToServerVars($headers + [
            'X-Device-Id' => $this->device->public_id, 'Content-Type' => 'application/json', 'Accept' => 'application/json',
        ]), $body)->assertStatus(400)->assertJsonPath('error.code', 'timestamp_skew')->assertJsonStructure(['error' => ['context' => ['server_time']]]);
    }

    public function test_replayed_nonce_is_rejected(): void
    {
        $this->registerDevice()->assertCreated();

        $body = json_encode(['phone' => '09121234567']);
        $headers = $this->signatureHeaders($this->deviceKey, 'POST', '/api/v1/auth/otp/request', $body) + [
            'X-Device-Id' => $this->device->public_id, 'Content-Type' => 'application/json', 'Accept' => 'application/json',
        ];
        $server = $this->transformHeadersToServerVars($headers);

        $this->call('POST', '/api/v1/auth/otp/request', [], [], [], $server, $body)->assertOk();
        $this->call('POST', '/api/v1/auth/otp/request', [], [], [], $server, $body)
            ->assertStatus(400)->assertJsonPath('error.code', 'nonce_reused');
    }

    public function test_tampered_body_is_rejected(): void
    {
        $this->registerDevice()->assertCreated();

        $signedBody = json_encode(['phone' => '09121234567']);
        $sentBody = json_encode(['phone' => '09129999999']);
        $headers = $this->signatureHeaders($this->deviceKey, 'POST', '/api/v1/auth/otp/request', $signedBody) + [
            'X-Device-Id' => $this->device->public_id, 'Content-Type' => 'application/json', 'Accept' => 'application/json',
        ];

        $this->call('POST', '/api/v1/auth/otp/request', [], [], [], $this->transformHeadersToServerVars($headers), $sentBody)
            ->assertStatus(400)->assertJsonPath('error.code', 'signature_invalid');
    }

    public function test_unknown_device_cannot_call_signed_routes(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '09121234567'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'device_not_registered')
            ->assertJsonStructure(['error' => ['code', 'message', 'request_id']]);
    }

    public function test_blocked_device_cannot_request_otp(): void
    {
        $this->registerDevice()->assertCreated();
        $this->device->forceFill(['status' => 'blocked'])->save();

        $this->signedJson('POST', '/api/v1/auth/otp/request', ['phone' => '09121234567'])
            ->assertForbidden()->assertJsonPath('error.code', 'device_blocked');
    }
}
