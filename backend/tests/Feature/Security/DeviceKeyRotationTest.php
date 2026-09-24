<?php

namespace Tests\Feature\Security;

use App\Domain\Device\Actions\RotateDeviceKey;
use App\Models\AuditLog;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class DeviceKeyRotationTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
    }

    private function derBase64(\OpenSSLAsymmetricKey $key): string
    {
        $pem = $this->publicPem($key);

        return preg_replace('/-----[^-]+-----|\s/', '', $pem);
    }

    /** Sends the rotation signed with the current key, with a proof from $proofKey. */
    private function rotate(\OpenSSLAsymmetricKey $newKey, ?\OpenSSLAsymmetricKey $proofKey = null, ?string $proofTimestamp = null)
    {
        $timestamp = (string) time();
        $der = $this->derBase64($newKey);
        openssl_sign(RotateDeviceKey::statement($this->device->public_id, $proofTimestamp ?? $timestamp, $der), $proof, $proofKey ?? $newKey, OPENSSL_ALGO_SHA256);
        $body = json_encode(['public_key' => $der, 'proof' => base64_encode($proof)]);

        return $this->call('POST', '/api/v1/devices/rotate-key', [], [], [], $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json', 'Accept' => 'application/json', 'X-Device-Id' => $this->device->public_id,
            ...($this->token ? ['Authorization' => 'Bearer '.$this->token] : []),
            ...$this->signatureHeaders($this->deviceKey, 'POST', '/api/v1/devices/rotate-key', $body, (int) $timestamp),
        ]), $body);
    }

    public function test_rotation_swaps_the_key_and_keeps_the_session(): void
    {
        $this->loginAs();
        $old = $this->deviceKey;
        $new = $this->newDeviceKey();

        $this->rotate($new)->assertOk()->assertJsonPath('data.key_version', 2);
        $this->assertTrue(AuditLog::query()->where('action', 'device.key_rotated')->exists());

        // Old key is dead, new key works with the same token.
        $this->signedJson('POST', '/api/v1/me/deletion-request')->assertStatus(400);
        $this->deviceKey = $new;
        $this->signedJson('POST', '/api/v1/me/deletion-request')->assertSuccessful();
        $this->assertNotNull($old);
    }

    public function test_proof_must_come_from_the_new_key_and_this_moment(): void
    {
        $this->loginAs();
        $new = $this->newDeviceKey();

        $this->rotate($new, proofKey: $this->newDeviceKey())->assertStatus(422)->assertJsonPath('error.code', 'invalid_proof');
        $this->rotate($new, proofTimestamp: (string) (time() - 3600))->assertStatus(422)->assertJsonPath('error.code', 'invalid_proof');
        $this->assertSame(1, $this->device->fresh()->key_version);
    }

    public function test_required_rotation_blocks_other_signed_calls_until_done(): void
    {
        $this->loginAs();
        $this->device->forceFill(['key_rotation_required' => true])->save();

        $this->signedJson('POST', '/api/v1/me/deletion-request')->assertStatus(428)->assertJsonPath('error.code', 'key_rotation_required');

        $new = $this->newDeviceKey();
        $this->rotate($new)->assertOk();
        $this->deviceKey = $new;
        $this->signedJson('POST', '/api/v1/me/deletion-request')->assertSuccessful();
    }
}
