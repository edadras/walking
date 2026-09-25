<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class ReferralLinksTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    public function test_share_url_and_landing_page(): void
    {
        $this->seed(PlatformSeeder::class);
        $user = $this->loginAs();
        $user->forceFill(['display_name' => 'سارا'])->save();
        $url = $this->authedJson('GET', '/api/v1/referral')->assertOk()->json('data.share_url');
        $this->assertSame(url('/r/'.$user->referral_code), $url);

        $this->get('/r/'.strtolower($user->referral_code))->assertOk()
            ->assertSee('سارا تو را به گام‌یار دعوت کرده')
            ->assertSee($user->referral_code)
            ->assertSee('gamyar://app/r/'.$user->referral_code, false)
            ->assertSee('referrer='.rawurlencode('code='.$user->referral_code), false);

        $this->get('/r/NOSUCHCODE')->assertOk()->assertSee('این لینک دعوت معتبر نیست')->assertDontSee('gamyar://');
        $this->get('/')->assertOk()->assertSee('پیاده‌روی کن، امتیاز بگیر');
    }

    public function test_banned_inviters_links_are_dead(): void
    {
        $u = User::factory()->create(['status' => 'banned']);
        $this->get('/r/'.$u->referral_code)->assertOk()->assertSee('معتبر نیست');
    }

    public function test_asset_links_lists_signing_keys(): void
    {
        $this->get('/.well-known/assetlinks.json')->assertOk()->assertExactJson([]);
        config(['walk.links.android_cert_sha256' => ['AA:BB'], 'walk.links.android_package' => 'ir.gamyar.app']);
        $this->get('/.well-known/assetlinks.json')->assertOk()
            ->assertJsonPath('0.target.package_name', 'ir.gamyar.app')
            ->assertJsonPath('0.target.sha256_cert_fingerprints', ['AA:BB'])
            ->assertJsonPath('0.relation.0', 'delegate_permission/common.handle_all_urls');
    }
}
