<?php

namespace Tests\Concerns;

use App\Domain\Device\RequestSignature;
use App\Jobs\SendOtpSms;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use OpenSSLAsymmetricKey;

/**
 * Plays the role of the mobile app: owns an EC P-256 key, registers the device
 * and signs requests exactly like the Flutter SigningInterceptor.
 */
trait SignsDeviceRequests
{
    protected ?OpenSSLAsymmetricKey $deviceKey = null;

    protected ?Device $device = null;

    protected ?string $token = null;

    protected function newDeviceKey(): OpenSSLAsymmetricKey
    {
        return openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    }

    protected function publicPem(OpenSSLAsymmetricKey $key): string
    {
        return openssl_pkey_get_details($key)['key'];
    }

    /** @return array<string, string> */
    protected function signatureHeaders(OpenSSLAsymmetricKey $key, string $method, string $path, string $body, ?int $timestamp = null, ?string $nonce = null): array
    {
        $timestamp = (string) ($timestamp ?? time());
        $nonce ??= Str::random(24);
        openssl_sign(RequestSignature::canonical($method, $path, $timestamp, $nonce, $body), $signature, $key, OPENSSL_ALGO_SHA256);

        return [
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => base64_encode($signature),
        ];
    }

    protected function registerDevice(array $overrides = []): TestResponse
    {
        $this->deviceKey ??= $this->newDeviceKey();

        $payload = array_merge([
            'install_id' => (string) Str::uuid(),
            'platform' => 'android',
            'os_version' => '14',
            'app_version' => '1.0.0',
            'model' => 'Pixel 8',
            'manufacturer' => 'Google',
            'public_key' => $this->publicPem($this->deviceKey),
        ], $overrides);

        $body = json_encode($payload);
        $response = $this->call('POST', '/api/v1/devices/register', [], [], [],
            $this->transformHeadersToServerVars($this->signatureHeaders($this->deviceKey, 'POST', '/api/v1/devices/register', $body) + [
                'Content-Type' => 'application/json', 'Accept' => 'application/json',
            ]), $body);

        if ($response->isSuccessful()) {
            $this->device = Device::query()->where('public_id', $response->json('data.device_id'))->first();
        }

        return $response;
    }

    /** Sends a JSON request signed with the current device key. */
    protected function signedJson(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $body = $data === [] && in_array($method, ['GET', 'DELETE'], true) ? '' : json_encode($data);
        $path = parse_url($uri, PHP_URL_PATH);

        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Device-Id' => $this->device->public_id,
        ], $this->token ? ['Authorization' => 'Bearer '.$this->token] : [], $this->signatureHeaders($this->deviceKey, $method, $path, $body), $headers);

        return $this->call($method, $uri, [], [], [], $this->transformHeadersToServerVars($headers), $body);
    }

    protected function authedJson(string $method, string $uri, array $data = []): TestResponse
    {
        // Per-request header (withHeaders() would leak the token into later requests).
        return $this->json($method, $uri, $data, ['Authorization' => 'Bearer '.$this->token]);
    }

    /** Full login through the real OTP flow; captures the code from the queued SMS job. */
    protected function loginAs(string $phone = '09121234567'): User
    {
        // OTP requests are anonymous (as in the app); drop any previous session.
        $this->token = null;
        $this->app['auth']->forgetGuards();

        if ($this->device === null) {
            $this->registerDevice()->assertSuccessful();
        }

        Bus::fake([SendOtpSms::class]);
        $this->signedJson('POST', '/api/v1/auth/otp/request', ['phone' => $phone])->assertOk();

        $code = null;
        Bus::assertDispatched(SendOtpSms::class, function ($job) use (&$code) {
            $code = $job->code;

            return true;
        });

        $response = $this->signedJson('POST', '/api/v1/auth/otp/verify', ['phone' => $phone, 'code' => $code])->assertOk();
        $this->token = $response->json('data.token');

        return User::query()->where('public_id', $response->json('data.user.id'))->firstOrFail();
    }
}
