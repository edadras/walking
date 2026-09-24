<?php

namespace Tests\Concerns;

use App\Enums\CampaignStatus;
use App\Enums\CouponStatus;
use App\Enums\DiscountType;
use App\Enums\LocationStatus;
use App\Enums\SponsorRole;
use App\Enums\SponsorStatus;
use App\Enums\VerificationMethod;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Location;
use App\Models\Sponsor;
use App\Models\SponsorUser;

trait CreatesSponsorOffers
{
    protected const LAT = 35.6997;

    protected const LNG = 51.3380;

    protected function sponsor(int $budget = 10_000, SponsorStatus $status = SponsorStatus::Approved): Sponsor
    {
        $s = Sponsor::query()->create(['name' => 'کافه نمونه', 'status' => $status]);
        $s->forceFill(['point_budget' => $budget])->save();

        return $s;
    }

    protected function sponsorUser(Sponsor $sponsor, SponsorRole $role = SponsorRole::Owner, ?string $email = null): SponsorUser
    {
        return SponsorUser::query()->create([
            'sponsor_id' => $sponsor->id, 'name' => 'کاربر '.$role->label(), 'email' => $email ?? $role->value.$sponsor->id.'@sponsor.test',
            'password' => 'secret-password', 'role' => $role,
        ]);
    }

    protected function location(Sponsor $sponsor, array $overrides = []): Location
    {
        return Location::query()->create([
            'sponsor_id' => $sponsor->id, 'name' => 'شعبه آزادی', 'address' => 'تهران', 'city' => 'تهران',
            'latitude' => self::LAT, 'longitude' => self::LNG, 'radius_m' => 60, 'status' => LocationStatus::Approved,
            ...$overrides,
        ]);
    }

    protected function campaign(Sponsor $sponsor, Location $location, array $overrides = []): Campaign
    {
        $c = Campaign::query()->create([
            'sponsor_id' => $sponsor->id, 'name' => 'قهوه به قدم', 'verification_method' => VerificationMethod::GeofenceStay,
            'min_stay_seconds' => 120, 'reward_points' => 50, 'max_rewards_per_user' => 1, 'cooldown_hours' => 24,
            'point_budget' => 5_000, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10), 'status' => CampaignStatus::Active,
            ...$overrides,
        ]);
        $c->locations()->attach($location);

        return $c;
    }

    protected function coupon(Sponsor $sponsor, array $overrides = []): Coupon
    {
        return Coupon::query()->create([
            'sponsor_id' => $sponsor->id, 'title' => '۲۰٪ تخفیف قهوه', 'discount_type' => DiscountType::Percent, 'discount_value' => 20,
            'valid_days' => 14, 'per_user_limit' => 1, 'status' => CouponStatus::Active, ...$overrides,
        ]);
    }

    /** A position `$metres` north of the test location. */
    protected function fix(float $metres = 10, float $accuracy = 12, bool $mock = false): array
    {
        return ['lat' => self::LAT + $metres / 111_320, 'lng' => self::LNG, 'accuracy' => $accuracy, 'mock' => $mock];
    }
}
