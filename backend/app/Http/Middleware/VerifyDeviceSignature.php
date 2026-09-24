<?php

namespace App\Http\Middleware;

use App\Domain\Device\SignatureGuard;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a request signed by the Keystore key of a registered, active device.
 * When a user token is present it must have been issued to this same device.
 */
class VerifyDeviceSignature
{
    public function __construct(private readonly SignatureGuard $guard) {}

    public function handle(Request $request, Closure $next): Response
    {
        $device = ResolveDevice::fromHeader($request);

        if ($device === null) {
            throw new ApiException('device_not_registered', 'دستگاه شناسایی نشد. لطفاً برنامه را دوباره باز کنید.', 401);
        }
        if (! $device->isActive()) {
            throw ApiException::forbidden('device_blocked', 'دسترسی این دستگاه محدود شده است. با پشتیبانی تماس بگیرید.');
        }

        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken && $token->device_id !== $device->id) {
            throw new ApiException('device_mismatch', 'لطفاً دوباره وارد حساب خود شوید.', 401);
        }

        $this->guard->assertValid($request, $device->public_key, 'd'.$device->id);

        // After a suspected compromise only the rotation itself is accepted (with the current key).
        if ($device->key_rotation_required && ! $request->routeIs('api.v1.devices.rotate-key')) {
            throw new ApiException('key_rotation_required', 'برای ادامه، کلید امنیتی دستگاه باید تازه شود.', 428);
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
