<?php

namespace App\Http\Presenters;

use App\Domain\Settings\Settings;
use App\Enums\VisitStatus;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Location;
use App\Models\Sponsor;
use App\Models\UserCoupon;
use App\Models\Visit;
use Illuminate\Support\Facades\Storage;

/** API shapes for sponsors, campaigns, visits and coupons (no internal ids, no secrets). */
class SponsorPresenter
{
    public function __construct(private readonly Settings $settings) {}

    public function sponsor(Sponsor $s): array
    {
        return ['id' => $s->public_id, 'name' => $s->name, 'logo_url' => $this->url($s->logo_path)];
    }

    public function location(Location $l, ?float $distance = null): array
    {
        return [
            'id' => $l->public_id,
            'name' => $l->name,
            'address' => $l->address,
            'city' => $l->city,
            'lat' => round($l->latitude, 6),
            'lng' => round($l->longitude, 6),
            'radius_m' => $l->radius_m,
            'open_now' => $l->isOpenAt(now()),
            'distance_m' => $distance === null ? null : (int) (round($distance / 10) * 10),
        ];
    }

    public function campaignSummary(Campaign $c, ?string $ineligible = null): array
    {
        return [
            'id' => $c->public_id,
            'name' => $c->name,
            'reward_points' => $c->reward_points,
            'coupon' => $c->coupon ? $this->couponOffer($c->coupon) : null,
            'verification_method' => $c->verification_method->value,
            'requires_qr' => $c->requiresQr(),
            'min_stay_seconds' => $c->min_stay_seconds,
            'ends_at' => $c->ends_at->toIso8601String(),
            'eligible' => $ineligible === null,
            'ineligible_reason' => $ineligible,
        ];
    }

    public function campaign(Campaign $c, ?string $ineligible, int $myRewards): array
    {
        return [
            ...$this->campaignSummary($c, $ineligible),
            'description' => $c->description,
            'image_url' => $this->url($c->image_path),
            'sponsor' => $this->sponsor($c->sponsor),
            'starts_at' => $c->starts_at->toIso8601String(),
            'max_rewards_per_user' => $c->max_rewards_per_user,
            'my_rewards' => $myRewards,
            'locations' => $c->locations->map(fn (Location $l) => $this->location($l))->values(),
        ];
    }

    public function couponOffer(Coupon $c): array
    {
        return [
            'id' => $c->public_id,
            'title' => $c->title,
            'description' => $c->description,
            'terms' => $c->terms,
            'discount_label' => $c->discountLabel(),
            'image_url' => $this->url($c->image_path),
            'point_cost' => $c->point_cost,
            'sponsor' => $c->sponsor ? $this->sponsor($c->sponsor) : null,
            'expires_at' => $c->expires_at?->toIso8601String(),
            'remaining' => $c->usage_limit === null ? null : max(0, $c->usage_limit - $c->claimed_count),
        ];
    }

    public function userCoupon(UserCoupon $u): array
    {
        $c = $u->coupon;

        return [
            'id' => $u->public_id,
            'code' => $u->code,
            'status' => $u->effectiveStatus()->value,
            'status_label' => $u->effectiveStatus()->label(),
            'title' => $c->title,
            'description' => $c->description,
            'terms' => $c->terms,
            'discount_label' => $c->discountLabel(),
            'shared_code' => $c->shared_code,
            'sponsor' => $c->sponsor ? $this->sponsor($c->sponsor) : null,
            'claimed_at' => $u->claimed_at->toIso8601String(),
            'expires_at' => $u->expires_at?->toIso8601String(),
            'used_at' => $u->used_at?->toIso8601String(),
        ];
    }

    public function visit(Visit $v, ?UserCoupon $coupon = null): array
    {
        $c = $v->campaign;
        $inside = $v->last_inside_at !== null && $v->last_inside_at->equalTo($v->last_ping_at);

        return [
            'id' => $v->public_id,
            'status' => $v->status->value,
            'campaign_id' => $c->public_id,
            'campaign_name' => $c->name,
            'location_id' => $v->location->public_id,
            'location_name' => $v->location->name,
            'stay_seconds' => $v->stay_seconds,
            'min_stay_seconds' => $c->min_stay_seconds,
            'requires_qr' => $c->requiresQr(),
            'qr_verified' => $v->qr_verified_at !== null,
            'inside' => $v->status === VisitStatus::Started && $inside,
            'points_awarded' => $v->points_awarded,
            'rejection_reason' => $v->rejection_reason,
            'ping_interval_s' => $this->settings->int('visits.ping_interval_s'),
            'entered_at' => $v->entered_at->toIso8601String(),
            'verified_at' => $v->verified_at?->toIso8601String(),
            'coupon' => $coupon ? $this->userCoupon($coupon) : null,
        ];
    }

    private function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
