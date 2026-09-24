<?php

namespace Tests\Feature\Ads;

use App\Enums\AdminRole;
use App\Enums\CampaignStatus;
use App\Enums\SponsorRole;
use App\Filament\Admin\Resources\AdCampaigns\Pages\ListAdCampaigns as AdminList;
use App\Filament\Sponsor\Resources\AdCampaigns\Pages\ListAdCampaigns as SponsorList;
use App\Models\Ad;
use App\Models\AdCampaign;
use App\Models\Admin;
use App\Models\AdPlacement;
use Database\Seeders\PlatformSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesSponsorOffers;
use Tests\TestCase;

class AdPanelsTest extends TestCase
{
    use CreatesSponsorOffers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
    }

    public function test_sponsor_submits_and_admin_approves_an_ad_campaign(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('sponsor'));
        $sponsor = $this->sponsor();
        $other = $this->sponsor();
        $campaign = AdCampaign::query()->create(['sponsor_id' => $sponsor->id, 'name' => 'بنر کافه', 'status' => CampaignStatus::Draft, 'starts_at' => now(), 'ends_at' => now()->addWeek()]);
        $campaign->placements()->attach(AdPlacement::query()->where('key', 'home_banner')->value('id'));
        $foreign = AdCampaign::query()->create(['sponsor_id' => $other->id, 'name' => 'رقیب', 'status' => CampaignStatus::Draft, 'starts_at' => now(), 'ends_at' => now()->addWeek()]);
        $this->actingAs($this->sponsorUser($sponsor, SponsorRole::Manager), 'sponsor');

        $this->get('/sponsor/ads')->assertOk();
        $this->get('/sponsor/ads/create')->assertOk();
        Livewire::test(SponsorList::class)->assertCanSeeTableRecords([$campaign])->assertCanNotSeeTableRecords([$foreign]);

        // No creative yet → refused.
        Livewire::test(SponsorList::class)->callTableAction('submit', $campaign);
        $this->assertSame(CampaignStatus::Draft, $campaign->fresh()->status);

        Ad::query()->create(['ad_campaign_id' => $campaign->id, 'format' => 'banner', 'title' => 'قهوه تازه']);
        Livewire::test(SponsorList::class)->callTableAction('submit', $campaign);
        $this->assertSame(CampaignStatus::PendingApproval, $campaign->fresh()->status);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(Admin::factory()->role(AdminRole::SponsorManager)->create(), 'admin');
        foreach (['/admin/ad-campaigns', '/admin/ad-placements', '/admin/ad-views', "/admin/ad-campaigns/{$campaign->public_id}/edit"] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin/ad-providers')->assertForbidden();
        Livewire::test(AdminList::class)->callTableAction('approve', $campaign);
        $this->assertSame(CampaignStatus::Active, $campaign->fresh()->status);
    }

    public function test_cashiers_have_no_ad_access_and_super_admin_manages_providers(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('sponsor'));
        $this->actingAs($this->sponsorUser($this->sponsor(), SponsorRole::Cashier), 'sponsor');
        $this->get('/sponsor/ads')->assertForbidden();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(Admin::factory()->role(AdminRole::SuperAdmin)->create(), 'admin');
        $this->get('/admin/ad-providers')->assertOk()->assertSee('/api/v1/webhooks/ads/yektanet');
    }
}
