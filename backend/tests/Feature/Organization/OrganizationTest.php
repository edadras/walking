<?php

namespace Tests\Feature\Organization;

use App\Domain\Organization\OrganizationService;
use App\Domain\Store\PaymentGateway;
use App\Enums\AdminRole;
use App\Enums\ChallengeStatus;
use App\Enums\ChallengeType;
use App\Filament\Admin\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Org\Pages\Billing;
use App\Filament\Org\Resources\Challenges\Pages\CreateChallenge;
use App\Filament\Org\Resources\Members\Pages\ListMembers;
use App\Filament\Org\Widgets\OrgOverview;
use App\Models\Admin;
use App\Models\Challenge;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OrganizationUser;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\SignsDeviceRequests;
use Tests\Fakes\FakeGateway;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        $this->org = Organization::query()->create(['name' => 'شرکت نمونه', 'join_code' => 'ACME2026', 'seats' => 3, 'seat_price_rial' => 1_000_000,
            'paid_until' => now()->addMonth()->toDateString(), 'departments' => ['فروش', 'فنی']]);
    }

    private function steps(User $u, int $perDay): void
    {
        [$from] = OrganizationService::week();
        DB::table('daily_activities')->insert(['user_id' => $u->id, 'local_date' => $from, 'verified_steps' => $perDay, 'goal_steps' => 7500, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_employee_joins_with_code_and_sees_colleague_ranking(): void
    {
        $colleague = User::factory()->create(['display_name' => 'رضا']);
        OrganizationMember::query()->create(['organization_id' => $this->org->id, 'user_id' => $colleague->id, 'department' => 'فنی', 'joined_at' => now()]);
        $this->steps($colleague, 9000);

        $me = $this->loginAs();
        $this->steps($me, 12000);
        $this->authedJson('GET', '/api/v1/organization')->assertOk()->assertJsonPath('data', null);
        $this->authedJson('POST', '/api/v1/organization/join', ['code' => 'nope'])->assertUnprocessable()->assertJsonPath('error.code', 'organization_not_found');
        $this->authedJson('POST', '/api/v1/organization/join', ['code' => 'acme2026', 'department' => 'حسابداری'])->assertUnprocessable()->assertJsonPath('error.code', 'department_invalid');
        $this->authedJson('POST', '/api/v1/organization/join', ['code' => 'acme2026', 'department' => 'فروش'])->assertCreated()
            ->assertJsonPath('data.name', 'شرکت نمونه')
            ->assertJsonPath('data.my_rank', 1)
            ->assertJsonPath('data.ranking.0.is_me', true)
            ->assertJsonPath('data.ranking.1.name', 'رضا')
            ->assertJsonPath('data.ranking.1.steps', 9000)
            // Departments with fewer than 3 people never expose an average.
            ->assertJsonPath('data.departments.0.avg_steps', null);

        $this->authedJson('PATCH', '/api/v1/organization', ['department' => 'فنی'])->assertOk()->assertJsonPath('data.department', 'فنی');
        $this->authedJson('PATCH', '/api/v1/organization', ['department' => 'x'])->assertUnprocessable();
        $this->authedJson('DELETE', '/api/v1/organization')->assertNoContent();
        $this->assertSame(1, $this->org->members()->count());
    }

    public function test_seats_subscription_and_one_org_at_a_time(): void
    {
        $user = $this->loginAs();
        $this->org->update(['paid_until' => now()->subDay()->toDateString()]);
        $this->authedJson('POST', '/api/v1/organization/join', ['code' => 'ACME2026'])->assertConflict()->assertJsonPath('error.code', 'organization_inactive');

        $this->org->update(['paid_until' => now()->addMonth()->toDateString(), 'seats' => 1]);
        OrganizationMember::query()->create(['organization_id' => $this->org->id, 'user_id' => User::factory()->create()->id, 'joined_at' => now()]);
        $this->authedJson('POST', '/api/v1/organization/join', ['code' => 'ACME2026'])->assertConflict()->assertJsonPath('error.code', 'organization_full');

        $other = Organization::query()->create(['name' => 'دیگر', 'join_code' => 'OTHER001', 'seats' => 10, 'seat_price_rial' => 1, 'paid_until' => now()->addMonth()->toDateString()]);
        $this->authedJson('POST', '/api/v1/organization/join', ['code' => 'OTHER001'])->assertCreated();
        $this->org->update(['seats' => 10]);
        $this->authedJson('POST', '/api/v1/organization/join', ['code' => 'ACME2026'])->assertConflict()->assertJsonPath('error.code', 'already_member');
        $this->assertSame($other->id, OrganizationMember::query()->where('user_id', $user->id)->value('organization_id'));
    }

    public function test_company_challenges_are_only_for_members(): void
    {
        $c = Challenge::query()->create(['title' => 'چالش شرکت', 'type' => ChallengeType::Steps, 'metric' => 'steps', 'target_value' => 50000, 'reward_points' => 0, 'reward_xp' => 100,
            'starts_at' => now()->subDay(), 'ends_at' => now()->addWeek(), 'status' => ChallengeStatus::Active, 'organization_id' => $this->org->id]);
        $this->loginAs();
        $this->authedJson('GET', '/api/v1/challenges')->assertOk()->assertJsonMissing(['title' => 'چالش شرکت']);
        $this->authedJson('GET', '/api/v1/challenges/'.$c->public_id)->assertNotFound();
        $this->signedJson('POST', '/api/v1/challenges/'.$c->public_id.'/join')->assertForbidden();

        $this->authedJson('POST', '/api/v1/organization/join', ['code' => 'ACME2026'])->assertCreated()->assertJsonPath('data.challenges.0.title', 'چالش شرکت');
        $this->authedJson('GET', '/api/v1/challenges')->assertJsonFragment(['title' => 'چالش شرکت', 'organization' => true]);
        $this->signedJson('POST', '/api/v1/challenges/'.$c->public_id.'/join')->assertCreated();
    }

    private function orgAdmin(string $role = 'admin'): OrganizationUser
    {
        Filament::setCurrentPanel(Filament::getPanel('org'));
        $u = OrganizationUser::query()->create(['organization_id' => $this->org->id, 'name' => 'منابع انسانی', 'email' => $role.'@acme.test', 'password' => 'secret-password-1', 'role' => $role]);
        $this->actingAs($u, 'org');

        return $u;
    }

    public function test_org_panel_shows_aggregates_and_creates_point_free_challenges(): void
    {
        foreach (range(1, 3) as $i) {
            $u = User::factory()->create();
            OrganizationMember::query()->create(['organization_id' => $this->org->id, 'user_id' => $u->id, 'joined_at' => now()]);
            $this->steps($u, 7000);
        }
        $this->orgAdmin();
        $this->get('/org')->assertOk();
        Livewire::test(OrgOverview::class)->assertSee('ACME2026')->assertSee('100٪')->assertSee('1,000');
        $this->get('/org/members')->assertOk();
        Livewire::test(ListMembers::class)->assertCanSeeTableRecords(OrganizationMember::all());

        Livewire::test(CreateChallenge::class)->fillForm([
            'title' => 'ماه سلامت', 'metric' => 'steps', 'target_value' => 100000, 'starts_at' => now(), 'ends_at' => now()->addMonth(), 'reward_xp' => 9999, 'status' => 'active',
        ])->call('create')->assertHasFormErrors(['reward_xp']);
        Livewire::test(CreateChallenge::class)->fillForm([
            'title' => 'ماه سلامت', 'metric' => 'steps', 'target_value' => 100000, 'starts_at' => now(), 'ends_at' => now()->addMonth(), 'reward_xp' => 200, 'status' => 'active',
        ])->call('create')->assertHasNoFormErrors();
        $c = Challenge::query()->where('title', 'ماه سلامت')->firstOrFail();
        $this->assertSame($this->org->id, $c->organization_id);
        $this->assertSame(0, $c->reward_points);
    }

    public function test_viewer_cannot_manage_and_other_panels_are_closed(): void
    {
        $this->orgAdmin('viewer');
        $this->get('/org/challenges/create')->assertForbidden();
        $this->get('/admin')->assertRedirect();
    }

    public function test_subscription_payment_extends_and_sets_seats(): void
    {
        $gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $gateway);
        $this->orgAdmin();
        $until = $this->org->paid_until;
        Livewire::test(Billing::class)->callAction('pay', ['seats' => 20, 'months' => 3])->assertRedirect();
        $authority = $gateway->requested[0]['authority'];
        $this->assertSame(60_000_000, $gateway->requested[0]['amountRial']);
        $gateway->verified[$authority] = 60_000_000;
        $this->get("/payments/zarinpal/callback?Authority={$authority}&Status=OK")->assertOk()->assertSee('اشتراک سازمانی');

        $org = $this->org->fresh();
        $this->assertSame(20, $org->seats);
        $this->assertSame($until->copy()->addDay()->addMonthsNoOverflow(3)->subDay()->toDateString(), $org->paid_until->toDateString());
    }

    public function test_admin_creates_organization_with_hr_login(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(Admin::factory()->role(AdminRole::SponsorManager)->create(), 'admin');
        Livewire::test(CreateOrganization::class)->fillForm([
            'name' => 'بانک نمونه', 'seats' => 200, 'seat_price_rial' => 1_200_000, 'owner_name' => 'خانم کریمی', 'owner_email' => 'hr@bank.test', 'owner_password' => 'a-long-temporary-pass',
        ])->call('create')->assertHasNoFormErrors();
        $org = Organization::query()->where('name', 'بانک نمونه')->firstOrFail();
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $org->join_code);
        $this->assertTrue(OrganizationUser::query()->where('email', 'hr@bank.test')->where('organization_id', $org->id)->exists());
    }
}
