<?php

namespace Tests\Feature\Sponsor;

use App\Domain\Sponsor\CouponService;
use App\Enums\AdminRole;
use App\Enums\CampaignStatus;
use App\Enums\LocationStatus;
use App\Enums\SponsorRole;
use App\Enums\SponsorStatus;
use App\Enums\UserCouponStatus;
use App\Filament\Admin\Resources\Campaigns\Pages\ListCampaigns as AdminListCampaigns;
use App\Filament\Admin\Resources\Sponsors\Pages\ListSponsors;
use App\Filament\Sponsor\Pages\BranchQr;
use App\Filament\Sponsor\Pages\RedeemCoupon;
use App\Filament\Sponsor\Pages\Register;
use App\Filament\Sponsor\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\Sponsor\Resources\Locations\Pages\CreateLocation;
use App\Filament\Sponsor\Resources\Locations\Pages\EditLocation;
use App\Filament\Sponsor\Widgets\SponsorOverview;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Sponsor;
use App\Models\SponsorUser;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesSponsorOffers;
use Tests\TestCase;

class SponsorPanelTest extends TestCase
{
    use CreatesSponsorOffers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('sponsor'));
    }

    public function test_guests_admins_and_app_users_cannot_enter(): void
    {
        $this->get('/sponsor')->assertRedirect('/sponsor/login');
        $this->actingAs(Admin::factory()->role(AdminRole::SuperAdmin)->create(), 'admin');
        $this->get('/sponsor')->assertRedirect('/sponsor/login');
        $this->actingAs(User::factory()->create());
        $this->get('/sponsor')->assertRedirect('/sponsor/login');
    }

    public function test_registration_creates_a_pending_sponsor_that_sees_only_its_status(): void
    {
        Livewire::test(Register::class)
            ->fillForm(['sponsor_name' => 'نانوایی سنگک', 'name' => 'رضا', 'phone' => '09121234567', 'email' => 'reza@bakery.test', 'password' => 'long-password-1', 'passwordConfirmation' => 'long-password-1'])
            ->call('register')
            ->assertHasNoFormErrors();

        $owner = SponsorUser::query()->where('email', 'reza@bakery.test')->sole();
        $this->assertSame(SponsorStatus::Pending, $owner->sponsor->status);
        $this->assertSame(SponsorRole::Owner, $owner->role);

        $this->actingAs($owner, 'sponsor');
        $this->get('/sponsor')->assertOk();
        Livewire::test(SponsorOverview::class)->assertSee('در انتظار تأیید');
        $this->get('/sponsor/locations')->assertForbidden();
        $this->get('/sponsor/branch-qr')->assertForbidden();
    }

    public function test_every_page_is_scoped_to_the_own_sponsor(): void
    {
        $mine = $this->sponsor();
        $theirs = $this->sponsor();
        $myCampaign = $this->campaign($mine, $this->location($mine), ['name' => 'کمپین من']);
        $theirLocation = $this->location($theirs, ['name' => 'شعبه رقیب']);
        $theirCampaign = $this->campaign($theirs, $theirLocation, ['name' => 'کمپین رقیب']);
        $this->actingAs($this->sponsorUser($mine), 'sponsor');

        foreach (['/sponsor', '/sponsor/locations', '/sponsor/campaigns', '/sponsor/coupons', '/sponsor/visits', '/sponsor/team', '/sponsor/branch-qr', '/sponsor/redeem', '/sponsor/campaigns/create', '/sponsor/locations/create'] as $url) {
            $this->get($url)->assertOk();
        }

        Livewire::test(ListCampaigns::class)->assertCanSeeTableRecords([$myCampaign])->assertCanNotSeeTableRecords([$theirCampaign]);
        $this->get("/sponsor/locations/{$theirLocation->public_id}/edit")->assertNotFound();
        $this->get('/sponsor/locations')->assertDontSee('شعبه رقیب');
    }

    public function test_roles_limit_what_staff_can_do(): void
    {
        $sponsor = $this->sponsor();
        $this->actingAs($this->sponsorUser($sponsor, SponsorRole::Cashier), 'sponsor');

        $this->get('/sponsor/branch-qr')->assertOk();
        $this->get('/sponsor/redeem')->assertOk();
        $this->get('/sponsor/campaigns')->assertForbidden();
        $this->get('/sponsor/team')->assertForbidden();
        $this->get('/sponsor/visits')->assertForbidden();
    }

    public function test_analysts_only_read_reports(): void
    {
        $sponsor = $this->sponsor();
        $this->actingAs($this->sponsorUser($sponsor, SponsorRole::Analyst), 'sponsor');
        $this->get('/sponsor/visits')->assertOk();
        $this->get('/sponsor/branch-qr')->assertForbidden();
    }

    public function test_new_and_moved_locations_need_review(): void
    {
        $sponsor = $this->sponsor();
        $this->actingAs($this->sponsorUser($sponsor, SponsorRole::Manager), 'sponsor');

        Livewire::test(CreateLocation::class)
            ->fillForm(['name' => 'شعبه ونک', 'city' => 'تهران', 'latitude' => 35.757, 'longitude' => 51.41, 'radius_m' => 50, 'opening_hours_text' => ['sat' => '09:00-13:00, 16:00-22:00', 'xyz' => '1-2']])
            ->call('create')
            ->assertHasNoFormErrors();

        $location = Location::query()->where('name', 'شعبه ونک')->sole();
        $this->assertSame($sponsor->id, $location->sponsor_id);
        $this->assertSame(LocationStatus::Pending, $location->status);
        $this->assertSame(['sat' => [['09:00', '13:00'], ['16:00', '22:00']]], $location->opening_hours);
        $this->assertNotEmpty($location->qr_secret);

        $location->forceFill(['status' => LocationStatus::Approved])->save();
        Livewire::test(EditLocation::class, ['record' => $location->public_id])->fillForm(['name' => 'ونک (جدید)'])->call('save')->assertHasNoFormErrors();
        $this->assertSame(LocationStatus::Approved, $location->fresh()->status, 'renaming keeps the approval');
        Livewire::test(EditLocation::class, ['record' => $location->public_id])->fillForm(['radius_m' => 250])->call('save');
        $this->assertSame(LocationStatus::Pending, $location->fresh()->status, 'widening the fence needs review');
    }

    public function test_campaign_submission_and_admin_approval(): void
    {
        $sponsor = $this->sponsor();
        $campaign = $this->campaign($sponsor, $this->location($sponsor), ['status' => CampaignStatus::Draft]);
        $this->actingAs($this->sponsorUser($sponsor, SponsorRole::Manager), 'sponsor');

        Livewire::test(ListCampaigns::class)->callTableAction('submit', $campaign);
        $this->assertSame(CampaignStatus::PendingApproval, $campaign->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'campaign.submitted')->where('actor_type', 'sponsor_user')->exists());

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(Admin::factory()->role(AdminRole::SponsorManager)->create(), 'admin');
        $this->get('/admin/sponsors')->assertOk();
        $this->get('/admin/visits')->assertOk();
        $this->get('/admin/locations')->assertOk();
        $this->get('/admin/coupons/create')->assertOk();
        $this->get("/admin/campaigns/{$campaign->public_id}/edit")->assertOk();
        $this->get("/admin/locations/{$campaign->locations->first()->public_id}/edit")->assertOk();
        Livewire::test(AdminListCampaigns::class)->callTableAction('approve', $campaign);
        $this->assertSame(CampaignStatus::Active, $campaign->fresh()->status);

        $pending = Sponsor::query()->create(['name' => 'تازه', 'status' => SponsorStatus::Pending]);
        Livewire::test(ListSponsors::class)->callTableAction('top_up', $pending, ['points' => 500, 'note' => 'فاکتور ۹'])
            ->callTableAction('approve', $pending);
        $this->assertSame(500, $pending->fresh()->point_budget);
        $this->assertSame(SponsorStatus::Approved, $pending->fresh()->status);
    }

    public function test_other_admin_roles_cannot_manage_sponsors(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(Admin::factory()->role(AdminRole::ContentEditor)->create(), 'admin');
        $this->get('/admin/sponsors')->assertForbidden();
        $this->get('/admin/campaigns')->assertForbidden();
    }

    public function test_branch_qr_renders_a_code_for_own_branch(): void
    {
        $sponsor = $this->sponsor();
        $location = $this->location($sponsor);
        $this->actingAs($this->sponsorUser($sponsor, SponsorRole::Cashier), 'sponsor');

        Livewire::test(BranchQr::class)->assertSet('locationId', $location->public_id)->assertSee('<svg', false);
    }

    public function test_cashier_redeems_from_the_panel(): void
    {
        $sponsor = $this->sponsor();
        $user = User::factory()->create();
        $uc = app(CouponService::class)->issue($user, $this->coupon($sponsor), 'test', null, 'k');
        $this->actingAs($this->sponsorUser($sponsor, SponsorRole::Cashier), 'sponsor');

        Livewire::test(RedeemCoupon::class)->fillForm(['code' => strtolower($uc->code)])->call('redeem')->assertNotified();
        $this->assertSame(UserCouponStatus::Used, $uc->fresh()->status);
    }
}
