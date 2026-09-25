<?php

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\Metrics;
use App\Domain\Sponsor\CouponService;
use App\Domain\Wallet\WalletService;
use App\Enums\AdminRole;
use App\Enums\SponsorRole;
use App\Enums\TransactionType;
use App\Enums\UserCouponStatus;
use App\Enums\VisitStatus;
use App\Filament\Admin\Widgets\EconomyOverview;
use App\Filament\Admin\Widgets\PointSourcesChart;
use App\Filament\Sponsor\Pages\Effectiveness;
use App\Models\Admin;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\PlatformSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesSponsorOffers;
use Tests\TestCase;

class BusinessDashboardsTest extends TestCase
{
    use CreatesSponsorOffers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
    }

    public function test_economy_splits_issued_spent_and_who_funded_it(): void
    {
        $w = app(WalletService::class);
        $u = User::factory()->create();
        $w->credit($u, 1000, TransactionType::WalkingReward, 'a', 'x');
        $w->credit($u, 300, TransactionType::SponsorReward, 'b', 'x');
        $w->credit($u, 200, TransactionType::AdReward, 'c', 'x');
        $w->debit($u, 400, TransactionType::Purchase, 'd', 'x');
        $w->credit($u, 50, TransactionType::Refund, 'e', 'x'); // not "issued"

        $e = app(Metrics::class)->economy(30);
        $this->assertSame(1500, $e['issued_total']);
        $this->assertSame(500, $e['funded_total']);
        $this->assertSame(400, $e['spent_total']);
        $this->assertSame(['walking_reward' => 1000, 'sponsor_reward' => 300, 'ad_reward' => 200], $e['issued']);
        $this->assertSame(1150, array_sum(app(Metrics::class)->liability()));
    }

    public function test_economy_page_is_for_finance(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(Admin::factory()->role(AdminRole::Finance)->create(), 'admin');
        $this->get('/admin/economy')->assertOk()->assertSee('تعادل اقتصاد امتیاز');
        Livewire::test(EconomyOverview::class)->assertSee('بدهی امتیازی فعلی')->assertSee('نسبت مصرف به صدور');
        Livewire::test(PointSourcesChart::class)->assertOk();
    }

    public function test_content_editors_cannot_see_the_economy(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(Admin::factory()->role(AdminRole::ContentEditor)->create(), 'admin');
        $this->get('/admin/economy')->assertForbidden();
    }

    public function test_sponsor_effectiveness_counts_only_verified_visits_and_own_campaigns(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('sponsor'));
        $mine = $this->sponsor();
        $coupon = $this->coupon($mine);
        $location = $this->location($mine);
        $campaign = $this->campaign($mine, $location, ['name' => 'کمپین پاییز', 'coupon_id' => $coupon->id]);
        $campaign->forceFill(['points_spent' => 600])->save();
        $other = $this->sponsor();
        $theirs = $this->campaign($other, $this->location($other), ['name' => 'کمپین رقیب']);

        [$a, $b] = [User::factory()->create(), User::factory()->create()];
        $visit = fn (User $u, VisitStatus $s) => Visit::query()->create(['user_id' => $u->id, 'campaign_id' => $campaign->id, 'location_id' => $location->id,
            'status' => $s, 'entered_at' => now(), 'last_ping_at' => now()]);
        $v1 = $visit($a, VisitStatus::Rewarded);
        $visit($a, VisitStatus::Verified);
        $v3 = $visit($b, VisitStatus::Rewarded);
        $visit($b, VisitStatus::Rejected);
        $coupons = app(CouponService::class);
        $coupons->issue($a, $coupon, 'visit', $v1->id, 'k1')->forceFill(['status' => UserCouponStatus::Used])->save();
        $coupons->issue($b, $coupon, 'visit', $v3->id, 'k2');
        $coupons->issue($b, $coupon, 'claim', null, 'k3'); // not from a visit

        $this->actingAs($this->sponsorUser($mine, SponsorRole::Analyst), 'sponsor');
        $this->get('/sponsor/effectiveness')->assertOk();
        Livewire::test(Effectiveness::class)
            ->assertCanSeeTableRecords([$campaign])->assertCanNotSeeTableRecords([$theirs])
            ->assertTableColumnStateSet('visits_verified', 3, $campaign)
            ->assertTableColumnStateSet('visitors', 2, $campaign)
            ->assertTableColumnStateSet('cost_per_visit', 200, $campaign)
            ->assertTableColumnStateSet('coupons_issued', 2, $campaign)
            ->assertTableColumnStateSet('coupons_used', 1, $campaign);
    }

    public function test_cashiers_do_not_see_the_report(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('sponsor'));
        $this->actingAs($this->sponsorUser($this->sponsor(), SponsorRole::Cashier), 'sponsor');
        $this->get('/sponsor/effectiveness')->assertForbidden();
    }
}
