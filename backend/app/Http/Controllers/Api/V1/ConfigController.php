<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Settings\FeatureFlags;
use App\Domain\Settings\Settings;
use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    /** Public bootstrap config. If a valid token is sent, flags are resolved for that user (rollouts). */
    public function __invoke(Request $request, Settings $settings, FeatureFlags $flags): JsonResponse
    {
        $userId = null;
        if ($bearer = $request->bearerToken()) {
            $token = PersonalAccessToken::findToken($bearer);
            $userId = $token?->tokenable_id;
        }

        $appVersion = $request->header('X-App-Version');
        $minVersion = (string) $settings->get('app.min_supported_version');

        return response()->json(['data' => [
            'server_time' => now()->timestamp,
            'update' => [
                'required' => $appVersion !== null && version_compare($appVersion, $minVersion, '<'),
                'min_version' => $minVersion,
                'latest_version' => $settings->get('app.latest_version'),
            ],
            'features' => $flags->forClient($userId, $appVersion, $request->header('X-Platform', 'android')),
            'settings' => $settings->public(),
            'map' => [
                // A keyed provider is always reached through our proxy; otherwise the admin-set URL (or the app default).
                'tile_url' => config('walk.map.upstream') ? url('/api/v1/map/tiles').'/{z}/{x}/{y}' : $settings->get('map.tile_url'),
                'proxied' => (bool) config('walk.map.upstream'),
                'attribution' => $settings->get('map.attribution'),
                'max_zoom' => (int) $settings->get('map.max_zoom'),
            ],
        ]]);
    }
}
