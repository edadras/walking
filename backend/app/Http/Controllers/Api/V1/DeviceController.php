<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Device\Actions\RegisterDevice;
use App\Domain\Device\RequestSignature;
use App\Domain\Device\SignatureGuard;
use App\Exceptions\ApiException;
use App\Domain\Device\Actions\RotateDeviceKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterDeviceRequest;
use App\Http\Requests\Api\V1\UpdatePushTokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeviceController extends Controller
{
    /** Registration is self-signed: the request must be signed with the private half of the submitted key. */
    public function register(RegisterDeviceRequest $request, SignatureGuard $guard, RegisterDevice $action): JsonResponse
    {
        $pem = RequestSignature::normalizePublicKey($request->string('public_key'));
        if ($pem === null) {
            throw ApiException::unprocessable('public_key_invalid', 'کلید امنیتی دستگاه معتبر نیست.');
        }
        $fingerprint = RequestSignature::fingerprint($pem);

        $guard->assertValid($request, $pem, 'k'.$fingerprint);

        $device = $action->handle($request->validated(), $pem, $fingerprint, $request->ip());

        return response()->json(['data' => [
            'device_id' => $device->public_id,
            'server_time' => now()->timestamp,
        ]], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function updatePushToken(UpdatePushTokenRequest $request): Response
    {
        $request->attributes->get('device')->forceFill([
            'push_provider' => $request->string('provider'),
            'push_token' => $request->string('token'),
        ])->save();

        return response()->noContent();
    }

    /** Rotates the device key (signed with the current key; proof signed with the new one). */
    public function rotateKey(Request $request, RotateDeviceKey $rotate): JsonResponse
    {
        $data = $request->validate(['public_key' => ['required', 'string', 'max:2000'], 'proof' => ['required', 'string', 'max:200']]);
        $device = $rotate->handle($request->attributes->get('device'), $data['public_key'], $data['proof'], (string) $request->header('X-Timestamp'));

        return response()->json(['data' => ['device_id' => $device->public_id, 'key_version' => $device->key_version]]);
    }

    /** Lets the app detect clock skew before signing anything. */
    public function time(Request $request): JsonResponse
    {
        return response()->json(['data' => ['server_time' => now()->timestamp]]);
    }
}
