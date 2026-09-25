<?php

namespace Tests\Feature\Sponsor;

use App\Domain\Store\PaymentGateway;
use App\Domain\Wallet\ConversionRate;
use App\Enums\SponsorRole;
use App\Filament\Sponsor\Pages\Billing;
use App\Models\Payment;
use App\Models\SponsorTopUp;
use Database\Seeders\PlatformSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesSponsorOffers;
use Tests\Fakes\FakeGateway;
use Tests\TestCase;

class SponsorTopUpTest extends TestCase
{
    use CreatesSponsorOffers, RefreshDatabase;

    private FakeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        $this->gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
        app(ConversionRate::class)->set(500);
        Filament::setCurrentPanel(Filament::getPanel('sponsor'));
    }

    public function test_owner_pays_and_budget_grows_once_after_verification(): void
    {
        $sponsor = $this->sponsor();
        $before = $sponsor->point_budget;
        $this->actingAs($this->sponsorUser($sponsor, SponsorRole::Owner), 'sponsor');
        $this->get('/sponsor/billing')->assertOk()->assertSee('قیمت هر امتیاز: 500 ریال');

        Livewire::test(Billing::class)->callAction('topup', ['amount' => 50_000_000])->assertRedirect('https://pay.test/'.$this->gateway->requested[0]['authority']);
        $topUp = SponsorTopUp::query()->firstOrFail();
        $this->assertSame(100_000, $topUp->points);
        $this->assertSame($before, $sponsor->fresh()->point_budget, 'nothing before payment');

        $authority = $this->gateway->requested[0]['authority'];
        $this->gateway->verified[$authority] = 50_000_000;
        $this->get("/payments/zarinpal/callback?Authority={$authority}&Status=OK")->assertOk()->assertSee('شارژ اعتبار')->assertSee('بازگشت به پنل اسپانسر');
        $this->get("/payments/zarinpal/callback?Authority={$authority}&Status=OK")->assertOk(); // refresh is harmless

        $this->assertSame($before + 100_000, $sponsor->fresh()->point_budget);
        $this->assertSame(SponsorTopUp::PAID, $topUp->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sponsor.budget_topped_up']);
        Livewire::test(Billing::class)->assertCanSeeTableRecords([$topUp]);
    }

    public function test_forged_or_underpaid_return_does_not_credit(): void
    {
        $sponsor = $this->sponsor();
        $this->actingAs($this->sponsorUser($sponsor, SponsorRole::Owner), 'sponsor');
        Livewire::test(Billing::class)->callAction('topup', ['amount' => 50_000_000]);
        $authority = $this->gateway->requested[0]['authority'];
        $this->gateway->verified[$authority] = 1_000; // gateway says a different amount was paid
        $this->get("/payments/zarinpal/callback?Authority={$authority}&Status=OK")->assertOk()->assertSee('انجام نشد');
        $this->assertSame(SponsorTopUp::FAILED, SponsorTopUp::query()->first()->status);
        $this->assertSame(Payment::FAILED, Payment::query()->first()->status);
        $this->assertSame($sponsor->point_budget, $sponsor->fresh()->point_budget);
    }

    public function test_limits_and_roles(): void
    {
        $sponsor = $this->sponsor();
        $this->actingAs($this->sponsorUser($sponsor, SponsorRole::Owner), 'sponsor');
        Livewire::test(Billing::class)->callAction('topup', ['amount' => 1_000])->assertHasActionErrors(['amount']);
        $this->assertSame(0, SponsorTopUp::query()->count());

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->actingAs($this->sponsorUser($sponsor, SponsorRole::Manager), 'sponsor');
        $this->get('/sponsor/billing')->assertForbidden();
    }

    public function test_lost_callback_is_settled_by_the_sweep(): void
    {
        $sponsor = $this->sponsor();
        $this->actingAs($this->sponsorUser($sponsor, SponsorRole::Owner), 'sponsor');
        Livewire::test(Billing::class)->callAction('topup', ['amount' => 20_000_000]);
        $authority = $this->gateway->requested[0]['authority'];
        $this->gateway->verified[$authority] = 20_000_000;
        $this->travel(30)->minutes();
        $this->artisan('payments:sweep')->assertSuccessful();
        $this->assertSame($sponsor->point_budget + 40_000, $sponsor->fresh()->point_budget);
    }
}
