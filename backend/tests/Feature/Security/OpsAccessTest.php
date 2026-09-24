<?php

namespace Tests\Feature\Security;

use App\Enums\AdminRole;
use App\Jobs\SendOtpSms;
use App\Models\Admin;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_horizon_is_for_super_admins_only(): void
    {
        $this->get('/horizon')->assertForbidden();

        $this->actingAs(Admin::factory()->role(AdminRole::Operations)->create(), 'admin');
        $this->get('/horizon')->assertForbidden();
    }

    public function test_super_admin_opens_horizon(): void
    {
        $this->actingAs(Admin::factory()->role(AdminRole::SuperAdmin)->create(), 'admin');
        $this->get('/horizon')->assertOk();
    }

    public function test_otp_jobs_are_encrypted_on_the_queue(): void
    {
        $this->assertContains(ShouldBeEncrypted::class, class_implements(SendOtpSms::class));
    }
}
