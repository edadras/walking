<?php

namespace App\Http\Controllers\Web;

use App\Domain\Settings\Settings;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function home(): View
    {
        return view('landing.home', ['stores' => config('walk.links.stores')]);
    }

    /** Public map of the last 24 hours of shared walks (anonymous coloured lines). */
    public function map(Settings $settings): View
    {
        return view('landing.map', [
            'tileUrl' => $settings->get('map.tile_url') ?: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            'attribution' => (string) $settings->get('map.attribution', 'OpenStreetMap'),
            'maxZoom' => $settings->int('map.max_zoom'),
        ]);
    }

    /**
     * Invite link. With the app installed Android opens it directly (App Links); otherwise
     * this page shows the code and store buttons. Play installs carry the code through the
     * install referrer; other stores need it typed at sign-up, so it's shown large and copyable.
     */
    public function referral(string $code): View
    {
        $code = strtoupper($code);
        $inviter = User::query()->where('referral_code', $code)->where('status', 'active')->first();
        $stores = config('walk.links.stores');
        if ($inviter !== null && ! empty($stores['play'])) {
            $stores['play'] .= (str_contains($stores['play'], '?') ? '&' : '?').'referrer='.rawurlencode('code='.$code);
        }

        return view('landing.referral', [
            'code' => $inviter ? $code : null,
            'inviter' => $inviter?->publicName(),
            'stores' => $stores,
            'appLink' => 'gamyar://app/r/'.$code,
        ]);
    }

    /** Digital Asset Links: lets Android open https://<host>/r/… links straight in the app. */
    public function assetLinks(): JsonResponse
    {
        $fingerprints = config('walk.links.android_cert_sha256');

        return response()->json($fingerprints === [] ? [] : [[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => ['namespace' => 'android_app', 'package_name' => config('walk.links.android_package'), 'sha256_cert_fingerprints' => $fingerprints],
        ]])->header('Cache-Control', 'public, max-age=3600');
    }
}
