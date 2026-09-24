<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Settings\Settings;
use App\Domain\Sponsor\Geo;
use App\Domain\Sponsor\VisitService;
use App\Enums\CampaignStatus;
use App\Enums\LocationStatus;
use App\Enums\SponsorStatus;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\SponsorPresenter;
use App\Models\Campaign;
use App\Models\Location;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * «جایزه‌های اطراف من». The user's coordinates are used for this query only
 * and are never stored or logged.
 */
class SponsorOfferController extends Controller
{
    public function __construct(private readonly SponsorPresenter $present, private readonly VisitService $visits) {}

    public function nearby(Request $request, Settings $settings): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.5'],
        ]);
        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];
        $radius = min((float) ($data['radius_km'] ?? 5), $settings->int('visits.nearby_max_km')) * 1000;
        [$minLat, $maxLat, $minLng, $maxLng] = Geo::box($lat, $lng, $radius);
        $user = $request->user();

        $locations = Location::query()
            ->where('status', LocationStatus::Approved)
            ->whereBetween('latitude', [$minLat, $maxLat])
            ->whereBetween('longitude', [$minLng, $maxLng])
            ->whereHas('sponsor', fn ($q) => $q->where('status', SponsorStatus::Approved))
            ->whereHas('campaigns', fn ($q) => $this->running($q))
            ->with(['sponsor', 'campaigns' => fn ($q) => $this->running($q)->with(['coupon.sponsor', 'sponsor'])])
            ->limit(300)
            ->get()
            ->map(fn (Location $l) => [$l, Geo::distanceM($lat, $lng, $l->latitude, $l->longitude)])
            ->filter(fn ($pair) => $pair[1] <= $radius)
            ->sortBy(fn ($pair) => $pair[1])
            ->take(50)
            ->map(fn ($pair) => [
                ...$this->present->location($pair[0], $pair[1]),
                'sponsor' => $this->present->sponsor($pair[0]->sponsor),
                'campaigns' => $pair[0]->campaigns->map(fn (Campaign $c) => $this->present->campaignSummary($c, $this->visits->ineligibility($user, $c, $pair[0])))->values(),
            ])
            ->values();

        return response()->json(['data' => $locations]);
    }

    public function campaign(Request $request, Campaign $campaign): JsonResponse
    {
        abort_unless($campaign->status === CampaignStatus::Active && $campaign->sponsor?->isApproved(), 404);
        $campaign->load(['sponsor', 'coupon.sponsor', 'locations' => fn ($q) => $q->where('status', LocationStatus::Approved)]);
        $user = $request->user();
        $first = $campaign->locations->first();
        $ineligible = ! $campaign->isRunning() ? 'campaign_ended' : ($first ? $this->visits->ineligibility($user, $campaign, $first) : 'campaign_unavailable');
        $mine = Visit::query()->where('user_id', $user->id)->where('campaign_id', $campaign->id)->where('status', VisitStatus::Rewarded)->count();

        return response()->json(['data' => $this->present->campaign($campaign, $ineligible, $mine)]);
    }

    private function running($q)
    {
        return $q->where('campaigns.status', CampaignStatus::Active)->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }
}
