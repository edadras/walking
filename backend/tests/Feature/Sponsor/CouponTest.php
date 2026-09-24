<?php

namespace Tests\Feature\Sponsor;

use App\Domain\Sponsor\CouponService;
use App\Domain\Sponsor\SponsorModeration;
use App\Domain\Wallet\WalletService;
use App\Enums\AdminRole;
use App\Enums\CampaignStatus;
use App\Enums\SponsorRole;
use App\Enums\SponsorStatus;
use App\Enums\TransactionType;
use App\Enums\UserCouponStatus;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserCoupon;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesSponsorOffers;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use CreatesSponsorOffers, RefreshDatabase, SignsDeviceRequests;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 12:00:00', 'Asia/Tehran')->utc());
        $this->seed(PlatformSeeder::class);
    }

    private function fund(User $user, int $points): void
    {
        app(WalletService::class)->credit($user, $points, TransactionType::Adjustment, 'fund:'.Str::uuid(), 'test');
    }

    private function claim(string $couponId, ?string $key = null)
    {
        return $this->signedJson('POST', "/api/v1/coupons/{$couponId}/claim", [], ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    public function test_claiming_with_points_is_atomic_and_idempotent(): void
    {
        $user = $this->loginAs();
        $this->fund($user, 150);
        $coupon = $this->coupon($this->sponsor(), ['claimable' => true, 'point_cost' => 100, 'per_user_limit' => 2]);

        $this->authedJson('GET', '/api/v1/coupons?tab=available')->assertOk()->assertJsonPath('data.0.point_cost', 100);

        $key = (string) Str::uuid();
        $first = $this->claim($coupon->public_id, $key)->assertCreated()->assertJsonPath('data.status', 'available');
        $this->claim($coupon->public_id, $key)->assertOk()->assertJsonPath('data.code', $first->json('data.code'));
        $this->assertSame(50, $user->wallet->fresh()->available_balance);

        // Not enough points: nothing is issued.
        $this->claim($coupon->public_id)->assertStatus(409)->assertJsonPath('error.code', 'insufficient_points');
        $this->assertSame(1, UserCoupon::query()->count());
        $this->assertSame(1, $coupon->fresh()->claimed_count);

        $this->authedJson('GET', '/api/v1/coupons')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.discount_label', '20٪ تخفیف');
    }

    public function test_limits_and_non_claimable_coupons(): void
    {
        $user = $this->loginAs();
        $this->fund($user, 1_000);
        $sponsor = $this->sponsor();

        $campaignOnly = $this->coupon($sponsor);
        $this->claim($campaignOnly->public_id)->assertStatus(422)->assertJsonPath('error.code', 'coupon_not_claimable');

        $once = $this->coupon($sponsor, ['claimable' => true, 'point_cost' => 10]);
        $this->claim($once->public_id)->assertCreated();
        $this->claim($once->public_id)->assertStatus(409)->assertJsonPath('error.code', 'coupon_unavailable');

        $soldOut = $this->coupon($sponsor, ['claimable' => true, 'usage_limit' => 0]);
        $this->claim($soldOut->public_id)->assertStatus(409);

        $this->signedJson('POST', "/api/v1/coupons/{$once->public_id}/claim", [], ['Idempotency-Key' => 'x'])->assertStatus(422);
        $this->assertSame(990, $user->wallet->fresh()->available_balance);
    }

    public function test_cashier_redeems_only_own_sponsor_codes_once(): void
    {
        $user = $this->loginAs();
        $sponsor = $this->sponsor();
        $other = $this->sponsor();
        $cashier = $this->sponsorUser($sponsor, SponsorRole::Cashier);
        $stranger = $this->sponsorUser($other, SponsorRole::Cashier);
        $coupons = app(CouponService::class);

        $uc = $coupons->issue($user, $this->coupon($sponsor), 'test', null, 'k1');
        $pretty = substr($uc->code, 0, 4).'-'.strtolower(substr($uc->code, 4));

        try {
            $coupons->redeem($stranger, $uc->code);
            $this->fail('cross-sponsor redemption must fail');
        } catch (ApiException $e) {
            $this->assertSame('coupon_not_found', $e->errorCode);
        }

        $redeemed = $coupons->redeem($cashier, $pretty);
        $this->assertSame(UserCouponStatus::Used, $redeemed->status);
        $this->assertSame($cashier->id, $redeemed->redeemed_by);
        $this->assertSame(1, $uc->coupon->fresh()->redeemed_count);
        $this->assertTrue(AuditLog::query()->where('action', 'coupon.redeemed')->exists());

        $this->expectException(ApiException::class);
        $coupons->redeem($cashier, $uc->code);
    }

    public function test_expired_coupons_cannot_be_redeemed_and_are_swept(): void
    {
        $user = $this->loginAs();
        $sponsor = $this->sponsor();
        $cashier = $this->sponsorUser($sponsor, SponsorRole::Cashier);
        $uc = app(CouponService::class)->issue($user, $this->coupon($sponsor, ['valid_days' => 3]), 'test', null, 'k');

        $this->travel(4)->days();
        $this->authedJson('GET', '/api/v1/coupons')->assertJsonPath('data.0.status', 'expired');
        try {
            app(CouponService::class)->redeem($cashier, $uc->code);
            $this->fail('expired coupon redeemed');
        } catch (ApiException $e) {
            $this->assertSame('coupon_expired', $e->errorCode);
        }

        $this->artisan('sponsors:housekeeping')->assertSuccessful();
        $this->assertSame(UserCouponStatus::Expired, $uc->fresh()->status);
    }

    public function test_moderation_is_audited_and_suspension_pauses_campaigns(): void
    {
        $admin = Admin::query()->create(['name' => 'm', 'email' => 'm@x.test', 'password' => 'secret-password', 'role' => AdminRole::SponsorManager]);
        $sponsor = $this->sponsor(budget: 0, status: SponsorStatus::Pending);
        $location = $this->location($sponsor);
        $campaign = $this->campaign($sponsor, $location);
        $mod = app(SponsorModeration::class);

        $mod->approveSponsor($sponsor, $admin);
        $mod->topUp($sponsor, 2_000, 'فاکتور ۱۲۳', $admin);
        $this->assertSame(2_000, $sponsor->fresh()->point_budget);

        $mod->suspendSponsor($sponsor->fresh(), 'تخلف', $admin);
        $this->assertSame(CampaignStatus::Paused, $campaign->fresh()->status);
        $this->assertSame(['sponsor.approved', 'sponsor.budget_topped_up', 'sponsor.suspended'],
            AuditLog::query()->orderBy('id')->pluck('action')->intersect(['sponsor.approved', 'sponsor.budget_topped_up', 'sponsor.suspended'])->values()->all());
    }
}
