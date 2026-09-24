<?php

namespace Tests\Feature\Admin;

use App\Domain\Settings\Settings;
use App\Enums\AdminRole;
use App\Enums\UserStatus;
use App\Filament\Admin\Pages\ManageSettings;
use App\Filament\Admin\Resources\Users\Pages\ViewUser;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function actingAsAdmin(AdminRole $role = AdminRole::SuperAdmin): Admin
    {
        $admin = Admin::factory()->role($role)->create();
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_app_users_cannot_reach_the_panel(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_super_admin_sees_every_section(): void
    {
        $this->actingAsAdmin();
        User::factory()->count(3)->create();

        foreach (['/admin', '/admin/users', '/admin/devices', '/admin/feature-flags', '/admin/settings', '/admin/audit-logs', '/admin/admins', '/admin/cms-pages', '/admin/faqs'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_roles_are_enforced(): void
    {
        $this->actingAsAdmin(AdminRole::ContentEditor);

        $this->get('/admin/cms-pages')->assertOk();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
        $this->get('/admin/admins')->assertForbidden();
    }

    public function test_settings_are_saved_and_audited(): void
    {
        $admin = $this->actingAsAdmin();

        Livewire::test(ManageSettings::class)
            ->set('data.activity__default_daily_goal', 9000)
            ->call('save')
            ->assertHasNoErrors();

        app()->forgetScopedInstances();
        $this->assertSame(9000, app(Settings::class)->get('activity.default_daily_goal'));

        $log = AuditLog::query()->where('action', 'settings.updated')->firstOrFail();
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame(7500, $log->old_values['activity.default_daily_goal']);
        $this->assertSame(9000, $log->new_values['activity.default_daily_goal']);
    }

    public function test_banning_a_user_revokes_tokens_and_is_audited(): void
    {
        $this->actingAsAdmin(AdminRole::FraudAnalyst);
        $user = User::factory()->create();
        $user->createToken('app');

        Livewire::test(ViewUser::class, ['record' => $user->public_id])
            ->callAction('ban', data: ['reason' => 'تقلب با GPS جعلی'])
            ->assertHasNoActionErrors();

        $this->assertSame(UserStatus::Banned, $user->refresh()->status);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame('تقلب با GPS جعلی', AuditLog::query()->where('action', 'user.status_changed')->value('meta')['reason']);
    }

    public function test_support_cannot_moderate_users(): void
    {
        $this->actingAsAdmin(AdminRole::Support);
        $user = User::factory()->create();

        Livewire::test(ViewUser::class, ['record' => $user->public_id])->assertActionHidden('ban');
    }

    public function test_audit_logs_are_immutable(): void
    {
        $log = AuditLog::query()->create(['actor_type' => 'system', 'action' => 'test']);

        $this->expectException(LogicException::class);
        $log->delete();
    }
}
