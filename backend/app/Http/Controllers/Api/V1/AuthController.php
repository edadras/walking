<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Actions\LoginWithOtp;
use App\Domain\Auth\OtpService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RequestOtpRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Http\Resources\V1\DeviceResource;
use App\Http\Resources\V1\MeResource;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function requestOtp(RequestOtpRequest $request, OtpService $otp): JsonResponse
    {
        $result = $otp->request($request->normalizedPhone(), $request->attributes->get('device'), $request->ip());

        return response()->json(['data' => $result]);
    }

    public function verifyOtp(VerifyOtpRequest $request, LoginWithOtp $login): JsonResponse
    {
        $result = $login->handle(
            $request->normalizedPhone(),
            $request->string('code'),
            $request->attributes->get('device'),
            $request->input('timezone'),
            $request->input('referral_code'),
        );

        return response()->json(['data' => [
            'token' => $result['token'],
            'is_new_user' => $result['is_new_user'],
            'user' => MeResource::make($result['user']->load('profile')),
        ]]);
    }

    public function logout(Request $request, AuditLogger $audit): Response
    {
        $request->user()->currentAccessToken()->delete();
        $audit->log('auth.logout', $request->user());

        return response()->noContent();
    }

    public function devices(Request $request): AnonymousResourceCollection
    {
        $devices = $request->user()->devices()
            ->whereIn('devices.id', PersonalAccessToken::query()
                ->where('tokenable_type', $request->user()->getMorphClass())
                ->where('tokenable_id', $request->user()->id)
                ->select('device_id'))
            ->orderByDesc('device_user_links.last_seen_at')
            ->get();

        return DeviceResource::collection($devices);
    }

    /** Signs the user out of one of their other devices. */
    public function revokeDevice(Request $request, Device $device, AuditLogger $audit): Response
    {
        $user = $request->user();
        abort_unless($user->devices()->whereKey($device->id)->exists(), 404);

        PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            ->where('device_id', $device->id)
            ->delete();

        $audit->log('auth.device_revoked', $device, meta: ['user' => $user->public_id]);

        return response()->noContent();
    }
}
