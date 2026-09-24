<?php

namespace App\Domain\Ads;

use App\Domain\Settings\FeatureFlags;
use App\Domain\Settings\Settings;
use App\Enums\CampaignStatus;
use App\Models\Ad;
use App\Models\AdCampaign;
use App\Models\AdEvent;
use App\Models\AdPlacement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Picks the ad for a placement: running campaign on that placement, creative of
 * the placement's format, limits and the per-user daily frequency cap not
 * reached, targeting matched. Weighted by priority.
 */
class AdServer
{
    public function __construct(private readonly Settings $settings, private readonly FeatureFlags $flags, private readonly ServeToken $tokens) {}

    public function enabledFor(User $user, AdPlacement $placement): bool
    {
        return $placement->is_active && $this->flags->enabled('ads', $user->id)
            && ($placement->format->value !== 'rewarded' || $this->flags->enabled('rewarded_ads', $user->id));
    }

    /** @return array{ad: Ad, serve_id: string, token: string}|null */
    public function serve(User $user, AdPlacement $placement): ?array
    {
        if (! $this->enabledFor($user, $placement)) {
            return null;
        }
        $ad = $this->pick($user, $placement);
        if ($ad === null) {
            return null;
        }
        $serveId = (string) Str::ulid();
        $token = $this->tokens->issue(['s' => $serveId, 'a' => $ad->id, 'c' => $ad->ad_campaign_id, 'p' => $placement->id, 'u' => $user->id, 'd' => $this->today($user)]);

        return ['ad' => $ad, 'serve_id' => $serveId, 'token' => $token];
    }

    public function pick(User $user, AdPlacement $placement): ?Ad
    {
        $candidates = $this->candidates($user, $placement);
        if ($candidates->isEmpty()) {
            return null;
        }
        $total = $candidates->sum(fn (AdCampaign $c) => max(1, $c->priority));
        $roll = random_int(1, $total);
        foreach ($candidates as $campaign) {
            $roll -= max(1, $campaign->priority);
            if ($roll <= 0) {
                return $campaign->ads->random();
            }
        }

        return $candidates->last()->ads->random();
    }

    /** @return Collection<int, AdCampaign> */
    private function candidates(User $user, AdPlacement $placement): Collection
    {
        $campaigns = AdCampaign::query()
            ->where('status', CampaignStatus::Active)
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->whereHas('placements', fn ($q) => $q->whereKey($placement->id))
            ->where(fn ($q) => $q->whereNull('impression_limit')->orWhereColumn('impressions_count', '<', 'impression_limit'))
            ->where(fn ($q) => $q->whereNull('click_limit')->orWhereColumn('clicks_count', '<', 'click_limit'))
            ->where(fn ($q) => $q->whereNull('sponsor_id')->orWhereHas('sponsor', fn ($s) => $s->where('status', 'approved')))
            ->with(['ads' => fn ($q) => $q->where('is_active', true)->where('format', $placement->format)])
            ->get()
            ->filter(fn (AdCampaign $c) => $c->ads->isNotEmpty() && $c->targets($user))
            ->filter(fn (AdCampaign $c) => $placement->format->value !== 'rewarded' || ($c->reward_points > 0 && $c->point_budget - $c->points_spent >= $c->reward_points));

        if ($campaigns->isEmpty()) {
            return $campaigns;
        }
        $seen = AdEvent::query()->where('user_id', $user->id)->where('local_date', $this->today($user))->where('type', 'impression')
            ->whereIn('ad_campaign_id', $campaigns->pluck('id'))
            ->selectRaw('ad_campaign_id, COUNT(*) n')->groupBy('ad_campaign_id')->pluck('n', 'ad_campaign_id');
        $default = $this->settings->int('ads.default_frequency_cap');

        return $campaigns->filter(fn (AdCampaign $c) => ($seen[$c->id] ?? 0) < ($c->frequency_cap_per_day ?? $default))->values();
    }

    public function today(User $user): string
    {
        return now($user->timezone)->toDateString();
    }
}
