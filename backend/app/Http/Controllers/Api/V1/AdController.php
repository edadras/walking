<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ads\AdEventRecorder;
use App\Domain\Ads\AdServer;
use App\Domain\Ads\ProviderCallbacks;
use App\Domain\Ads\RewardedAds;
use App\Domain\Settings\Settings;
use App\Enums\AdViewStatus;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdPlacement;
use App\Models\AdProvider;
use App\Models\AdView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdController extends Controller
{
    public function __construct(private readonly AdServer $server) {}

    public function placement(Request $request, string $key): JsonResponse
    {
        $placement = AdPlacement::query()->where('key', $key)->first();
        $served = $placement ? $this->server->serve($request->user(), $placement) : null;

        return response()->json(['data' => $served ? [...$this->ad($served['ad']), 'token' => $served['token']] : null]);
    }

    public function events(Request $request, AdEventRecorder $recorder): JsonResponse
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'max:50'],
            'events.*.token' => ['required', 'string', 'max:600'],
            'events.*.type' => ['required', 'in:impression,click'],
        ]);

        return response()->json(['data' => $recorder->record($request->user(), $data['events'])]);
    }

    public function rewardedStatus(Request $request, Settings $settings): JsonResponse
    {
        $user = $request->user();
        $placement = AdPlacement::query()->where('key', $request->query('placement', 'rewarded_default'))->first();
        $enabled = $placement !== null && $placement->format->value === 'rewarded' && $this->server->enabledFor($user, $placement);
        $done = AdView::query()->where('user_id', $user->id)->where('local_date', $this->server->today($user))->where('status', AdViewStatus::Rewarded)->count();
        $cap = $settings->int('ads.rewarded_daily_cap');

        return response()->json(['data' => [
            'enabled' => $enabled && $this->server->pick($user, $placement) !== null,
            'remaining_today' => max(0, $cap - $done),
            'daily_cap' => $cap,
        ]]);
    }

    public function startRewarded(Request $request, RewardedAds $rewarded): JsonResponse
    {
        $data = $request->validate(['placement' => ['required', 'string', 'max:48']]);
        $placement = AdPlacement::query()->where('key', $data['placement'])->firstOrFail();
        $view = $rewarded->start($request->user(), $request->attributes->get('device'), $placement);

        return response()->json(['data' => $this->view($view)], 201);
    }

    public function completeRewarded(Request $request, AdView $view, RewardedAds $rewarded): JsonResponse
    {
        abort_unless($view->user_id === $request->user()->id, 404);

        return response()->json(['data' => $this->view($rewarded->completeInternal($request->user(), $view))]);
    }

    /** S2S callback (no user auth; authenticated by the provider's HMAC). */
    public function webhook(Request $request, string $provider, ProviderCallbacks $callbacks, RewardedAds $rewarded): JsonResponse
    {
        $model = AdProvider::query()->where('key', $provider)->first();
        $verified = $model ? $callbacks->verify($model, $request) : null;
        if ($verified === null) {
            return response()->json(['error' => ['code' => 'invalid_signature', 'message' => 'Invalid callback.']], 403);
        }
        $view = $rewarded->completeFromProvider($model, $verified['view_id'], $verified['transaction_id']);

        return response()->json(['data' => ['status' => $view->status->value]]);
    }

    private function ad(Ad $ad): array
    {
        return [
            'id' => $ad->public_id,
            'format' => $ad->format->value,
            'title' => $ad->title,
            'body' => $ad->body,
            'image_url' => $ad->image_path ? Storage::disk('public')->url($ad->image_path) : null,
            'cta_label' => $ad->cta_label,
            'action_url' => $ad->action_url,
            'min_view_seconds' => $ad->min_view_seconds,
            'advertiser' => $ad->campaign->sponsor?->name,
        ];
    }

    private function view(AdView $view): array
    {
        $view->loadMissing('ad.campaign.sponsor');

        return [
            'id' => $view->public_id,
            'status' => $view->status->value,
            'ad' => $this->ad($view->ad),
            'reward_points' => $view->points_awarded ?: min($view->ad->campaign->reward_points, app(Settings::class)->int('ads.rewarded_max_points')),
            'points_awarded' => $view->points_awarded,
            'rejection_reason' => $view->rejection_reason,
            'started_at' => $view->started_at->toIso8601String(),
        ];
    }
}
