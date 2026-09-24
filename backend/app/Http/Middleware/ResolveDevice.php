<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For authenticated, non-signed routes: the device is taken from the token
 * (never from a header the client could swap) and must still be active.
 * Also rejects inactive users with a stable error code.
 */
class ResolveDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && $token->device_id !== null) {
            $device = $token->device;
            if ($device === null || ! $device->isActive()) {
                $token->delete();
                throw new ApiException('device_blocked', 'دسترسی این دستگاه محدود شده است. با پشتیبانی تماس بگیرید.', 403);
            }
            $request->attributes->set('device', $device);

            if ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subMinutes(10))) {
                $device->forceFill(['last_seen_at' => now()])->saveQuietly();
            }
        }

        if ($user !== null) {
            if (! $user->isActive()) {
                throw ApiException::forbidden('account_'.$user->status->value, match ($user->status->value) {
                    'suspended' => 'حساب شما موقتاً تعلیق شده است. برای اطلاعات بیشتر با پشتیبانی تماس بگیرید.',
                    default => 'حساب شما مسدود شده است.',
                });
            }
            if ($user->last_active_at === null || $user->last_active_at->lt(now()->subMinutes(10))) {
                $user->forceFill(['last_active_at' => now()])->saveQuietly();
            }
        }

        return $next($request);
    }

    public static function fromHeader(Request $request): ?Device
    {
        $id = (string) $request->header('X-Device-Id', '');

        return preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $id)
            ? Device::query()->where('public_id', strtolower($id))->first()
            : null;
    }
}
