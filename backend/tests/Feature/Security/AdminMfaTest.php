<?php

namespace Tests\Feature\Security;

use App\Enums\AdminRole;
use App\Models\Admin;
use Database\Seeders\PlatformSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMfaTest extends TestCase
{
    use RefreshDatabase;

    /** The requirement is read when panel routes are registered, so boot with it on. */
    public function createApplication()
    {
        putenv('ADMIN_MFA_REQUIRED=true');
        $_ENV['ADMIN_MFA_REQUIRED'] = $_SERVER['ADMIN_MFA_REQUIRED'] = 'true';

        return parent::createApplication();
    }

    protected function tearDown(): void
    {
        putenv('ADMIN_MFA_REQUIRED=false');
        $_ENV['ADMIN_MFA_REQUIRED'] = $_SERVER['ADMIN_MFA_REQUIRED'] = 'false';
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admins_without_totp_are_sent_to_set_it_up_when_required(): void
    {
        $admin = Admin::factory()->role(AdminRole::SuperAdmin)->create();
        $this->actingAs($admin, 'admin');

        $response = $this->get('/admin/users');
        $response->assertRedirect();
        $this->assertStringContainsString('multi-factor', $response->headers->get('Location'));
    }

    public function test_admins_with_totp_pass(): void
    {
        $admin = Admin::factory()->role(AdminRole::SuperAdmin)->create();
        $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $this->assertNotSame('JBSWY3DPEHPK3PXP', $admin->getRawOriginal('two_factor_secret'), 'stored encrypted');
        $this->actingAs($admin, 'admin');

        $this->get('/admin/users')->assertOk();
    }
}
