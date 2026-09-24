<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Settings\Settings;
use App\Models\CmsPage;
use App\Models\FeatureFlag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_public_settings_and_flags_only(): void
    {
        $data = $this->getJson('/api/v1/config')->assertOk()->json('data');

        $this->assertTrue($data['features']['store']);
        $this->assertFalse($data['features']['ads']);
        $this->assertSame(7500, $data['settings']['activity.default_daily_goal']);
        $this->assertArrayNotHasKey('auth.otp_length', $data['settings']);
    }

    public function test_admin_overrides_apply_immediately(): void
    {
        app(Settings::class)->set('activity.default_daily_goal', 10000);
        FeatureFlag::query()->create(['key' => 'store', 'is_enabled' => false]);
        app()->forgetScopedInstances();

        $data = $this->getJson('/api/v1/config')->json('data');

        $this->assertSame(10000, $data['settings']['activity.default_daily_goal']);
        $this->assertFalse($data['features']['store']);
    }

    public function test_flags_the_app_for_forced_update(): void
    {
        app(Settings::class)->set('app.min_supported_version', '1.2.0');

        $this->getJson('/api/v1/config', ['X-App-Version' => '1.1.9'])->assertJsonPath('data.update.required', true);
        $this->getJson('/api/v1/config', ['X-App-Version' => '1.2.0'])->assertJsonPath('data.update.required', false);
    }

    public function test_min_app_version_gates_a_feature(): void
    {
        FeatureFlag::query()->create(['key' => 'referral', 'is_enabled' => true, 'min_app_version' => '1.3.0']);

        $this->getJson('/api/v1/config', ['X-App-Version' => '1.2.0'])->assertJsonPath('data.features.referral', false);
        $this->getJson('/api/v1/config', ['X-App-Version' => '1.3.0'])->assertJsonPath('data.features.referral', true);
    }

    public function test_cms_pages_and_structured_404(): void
    {
        CmsPage::query()->create(['slug' => 'terms', 'title' => 'قوانین', 'body' => 'متن']);

        $this->getJson('/api/v1/pages/terms')->assertOk()->assertJsonPath('data.title', 'قوانین');
        $this->getJson('/api/v1/pages/missing')->assertNotFound()->assertJsonPath('error.code', 'not_found');
    }
}
